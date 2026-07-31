<?php

namespace App\Jobs\WechatOfficialAccount;

use App\Actions\Reception\AppendWechatOfficialAccountVisitorImageAction;
use App\Actions\Reception\AppendWechatOfficialAccountVisitorMessageAction;
use App\Enums\ConversationInboxStatus;
use App\Models\WechatInboundMessage;
use App\Services\Reception\ReceptionPipelineDispatcher;
use App\Services\Wechat\WechatOfficialAccountApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** 处理已持久化的微信公众号入站消息。 */
class ProcessWechatOfficialAccountMessageJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30];

    /** 创建微信公众号入站消息任务。 */
    public function __construct(
        public readonly string $inboundMessageId,
        public readonly ?string $reservationToken = null,
    ) {
        $this->queue = 'channel-inbound';
    }

    /** 领取入站台账，落库文本或图片并触发接待流程。 */
    public function handle(
        AppendWechatOfficialAccountVisitorMessageAction $appendText,
        AppendWechatOfficialAccountVisitorImageAction $appendImage,
        ReceptionPipelineDispatcher $pipeline,
        WechatOfficialAccountApi $api,
    ): void {
        $inbound = WechatInboundMessage::query()->find($this->inboundMessageId);
        if ($inbound === null) {
            Log::warning('微信公众号入站任务找不到处理台账。', ['inbound_message_id' => $this->inboundMessageId]);

            return;
        }

        $claimToken = $inbound->claimForProcessing($this->reservationToken);
        if ($claimToken === null) {
            return;
        }

        try {
            $this->process($inbound, $appendText, $appendImage, $pipeline, $api);
            if (! $inbound->markProcessed($claimToken)) {
                Log::warning('微信公众号入站消息完成时已失去处理租约。', [
                    'inbound_message_id' => (string) $inbound->id,
                    'provider_message_id' => $inbound->provider_message_id,
                ]);
            }
        } catch (Throwable $exception) {
            $delay = $this->retryDelay($inbound->attempts);
            $released = $inbound->releaseForRetry($claimToken, $exception->getMessage(), $delay);
            Log::warning('微信公众号入站消息处理失败，等待队列重试。', [
                'inbound_message_id' => (string) $inbound->id,
                'provider_message_id' => $inbound->provider_message_id,
                'attempt' => $inbound->attempts,
                'retry_delay_seconds' => $delay,
                'lease_released' => $released,
                'exception' => $exception::class,
                'reason' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }

    /** 解析一条微信文本或图片消息。 */
    private function process(
        WechatInboundMessage $inbound,
        AppendWechatOfficialAccountVisitorMessageAction $appendText,
        AppendWechatOfficialAccountVisitorImageAction $appendImage,
        ReceptionPipelineDispatcher $pipeline,
        WechatOfficialAccountApi $api,
    ): void {
        $payload = $inbound->payload;
        $messageType = strtolower((string) ($payload['MsgType'] ?? ''));
        $providerMessageId = $this->scalarString($payload['MsgId'] ?? null);
        $openid = $this->scalarString($payload['FromUserName'] ?? null);

        if (! in_array($messageType, ['text', 'image'], true)) {
            if ($openid !== null && $openid !== '' && $this->shouldNotifyUnsupported($messageType)) {
                $channel = $inbound->channel()->firstOrFail();
                $api->sendText($channel, $openid, __('conversation.wechat_unsupported_message'));
            }
            Log::info('微信公众号暂不支持的入站消息已记录。', [
                'inbound_message_id' => (string) $inbound->id,
                'provider_message_id' => $inbound->provider_message_id,
                'message_type' => $messageType === '' ? 'unknown' : $messageType,
            ]);

            return;
        }

        if ($openid === null || $openid === '' || $providerMessageId === null || $providerMessageId === '') {
            throw new \UnexpectedValueException('微信公众号入站消息缺少访客或消息标识。');
        }

        $displayName = $this->scalarString($payload['Nickname'] ?? null);
        $language = $this->scalarString($payload['Language'] ?? null);
        $channel = $inbound->channel()->firstOrFail();
        if ($messageType === 'text') {
            $text = $this->scalarString($payload['Content'] ?? null);
            if ($text === null || trim($text) === '') {
                throw new \UnexpectedValueException('微信公众号入站文本缺少正文。');
            }
            $result = $appendText->handle(
                channelCode: $channel->code,
                openid: $openid,
                text: $text,
                wechatMessageId: $providerMessageId,
                displayName: $displayName,
                language: $language,
            );
            $messageId = (string) $result['message']->id;
            $content = $text;
            $mediaIds = [];
        } else {
            $mediaId = $this->scalarString($payload['MediaId'] ?? null);
            if ($mediaId === null || $mediaId === '') {
                throw new \UnexpectedValueException('微信公众号入站图片缺少 MediaId。');
            }
            $image = $api->downloadImage($channel, $mediaId);
            $result = $appendImage->handle(
                channelCode: $channel->code,
                openid: $openid,
                wechatMessageId: $providerMessageId,
                contents: $image['contents'],
                fileName: $image['file_name'],
                mimeType: $image['mime_type'],
                displayName: $displayName,
                language: $language,
            );
            $messageId = '';
            $content = '';
            $mediaIds = [(string) $result['message']->id];
        }

        $conversation = $result['conversation'];
        if ($conversation->inbox_status === ConversationInboxStatus::AiHandling) {
            $pipeline->enqueueVisitorMessage((string) $conversation->id, $content, $messageId, $mediaIds);
        }

        Log::info('微信公众号入站消息处理完成。', [
            'inbound_message_id' => (string) $inbound->id,
            'provider_message_id' => $providerMessageId,
            'conversation_id' => (string) $conversation->id,
            'message_type' => $messageType,
        ]);
    }

    /** 判断不支持的类型是否属于用户主动发送的普通消息。 */
    private function shouldNotifyUnsupported(string $messageType): bool
    {
        return in_array($messageType, ['voice', 'video', 'shortvideo', 'location', 'link'], true);
    }

    /** 将第三方消息字段收窄为字符串。 */
    private function scalarString(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }

    /** 按当前处理次数返回下次重试等待秒数。 */
    private function retryDelay(int $attempts): int
    {
        return $attempts <= 1 ? 5 : 30;
    }

    /** 将耗尽重试的入站台账标记为失败。 */
    public function failed(Throwable $exception): void
    {
        $inbound = WechatInboundMessage::query()->find($this->inboundMessageId);
        $inbound?->failIfUnprocessed($exception->getMessage());
        Log::warning('微信公众号入站任务已耗尽重试。', [
            'inbound_message_id' => $this->inboundMessageId,
            'provider_message_id' => $inbound?->provider_message_id,
            'exception' => $exception::class,
            'reason' => $exception->getMessage(),
        ]);
    }
}
