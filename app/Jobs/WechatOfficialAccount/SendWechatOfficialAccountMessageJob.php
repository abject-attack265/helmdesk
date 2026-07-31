<?php

namespace App\Jobs\WechatOfficialAccount;

use App\Actions\Reception\ResolveWechatOfficialAccountReceptionContextAction;
use App\Data\Channel\WechatOfficialAccount\ChannelWechatOfficialAccountSettingsData;
use App\Enums\ChannelType;
use App\Enums\IdentityType;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageKind;
use App\Enums\MessageOutboxStatus;
use App\Exceptions\WechatApiException;
use App\Models\Attachment;
use App\Models\Channel;
use App\Models\ContactIdentity;
use App\Models\ConversationMessage;
use App\Models\MessageOutbox;
use App\Services\Wechat\WechatOfficialAccountApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 通过微信公众号客服消息 API 发送 AI / 客服文本，并回写消息投递状态。
 */
class SendWechatOfficialAccountMessageJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 30];

    /** 创建微信公众号出站任务。 */
    public function __construct(public readonly string $messageId)
    {
        $this->queue = 'channel-outbound';
    }

    /** 领取 Outbox 并发送微信公众号客服消息。 */
    public function handle(WechatOfficialAccountApi $api): void
    {
        $message = ConversationMessage::query()
            ->with(['conversation.channel', 'conversation.contact'])
            ->find($this->messageId);

        $outbox = MessageOutbox::query()
            ->where('conversation_message_id', $this->messageId)
            ->first();

        if ($message === null) {
            $outbox?->failIfUnsent('对应的会话消息不存在。');
            Log::warning('微信公众号出站任务找不到对应会话消息。', [
                'message_id' => $this->messageId,
                'outbox_id' => $outbox === null ? null : (string) $outbox->id,
            ]);

            return;
        }

        if ($outbox === null) {
            Log::warning('微信公众号出站任务缺少 Outbox。', [
                'message_id' => $this->messageId,
            ]);

            return;
        }

        $messagePayload = $message->payload ?? [];

        if ($outbox->status === MessageOutboxStatus::Sent) {
            if ($message->delivery_status !== MessageDeliveryStatus::Sent) {
                $message->update(['delivery_status' => MessageDeliveryStatus::Sent]);
            }

            return;
        }

        if ($outbox->status === MessageOutboxStatus::Failed) {
            if ($message->delivery_status !== MessageDeliveryStatus::Failed) {
                $message->update(['delivery_status' => MessageDeliveryStatus::Failed]);
            }

            return;
        }

        if ($message->recalled_at !== null) {
            $outbox->cancelPending('消息已撤回，取消外部渠道投递。');
            $this->syncMessageProjection($message, $outbox);
            Log::info('微信公众号待发送消息已因撤回取消。', [
                'message_id' => $this->messageId,
                'outbox_id' => (string) $outbox->id,
            ]);

            return;
        }

        $claimToken = $outbox->claimForSending();
        if ($claimToken === null) {
            $this->syncMessageProjection($message, $outbox);

            return;
        }

        Log::info('微信公众号出站 Outbox 已领取。', [
            'message_id' => $this->messageId,
            'outbox_id' => (string) $outbox->id,
            'channel_id' => (string) $outbox->channel_id,
            'attempt' => $outbox->attempts,
        ]);

        try {
            if (! in_array($message->kind, [MessageKind::Text, MessageKind::Image], true)
                || ($message->kind === MessageKind::Text && ! filled($message->content))) {
                $this->markClaimedFailed($message, $outbox, $claimToken, '微信公众号仅支持文字和图片出站消息。');

                return;
            }

            $conversation = $message->conversation;
            $channel = $conversation?->channel;
            $settings = $channel?->settings;
            if ($channel === null
                || $channel->type !== ChannelType::WechatOfficialAccount
                || ! $settings instanceof ChannelWechatOfficialAccountSettingsData
                || ! $settings->isConfigured()) {
                $this->markClaimedFailed($message, $outbox, $claimToken, '微信公众号出站消息找不到有效渠道凭证。');

                return;
            }

            $openid = ContactIdentity::query()
                ->where('contact_id', $conversation->contact_id)
                ->where('type', IdentityType::ChannelAccount)
                ->where('namespace', ResolveWechatOfficialAccountReceptionContextAction::identityNamespace(
                    $channel->code,
                    $settings->app_id,
                ))
                ->value('value');

            if (! is_string($openid) || $openid === '') {
                Log::warning('微信公众号出站消息找不到目标 OpenID，标记投递失败。', [
                    'message_id' => $this->messageId,
                    'conversation_id' => (string) $conversation->id,
                ]);
                $this->markClaimedFailed($message, $outbox, $claimToken, '微信公众号出站消息找不到目标 OpenID。');

                return;
            }

            if ($message->kind === MessageKind::Image) {
                $this->sendImage($api, $message, $outbox, $claimToken, $channel, $openid, $messagePayload);

                return;
            }

            $chunks = WechatOfficialAccountApi::splitText((string) $message->content);
            $outboxPayload = $outbox->payload ?? [];
            $wechatPayload = $outboxPayload['wechat_oa'] ?? [];
            $sentChunks = array_values(array_unique(array_map('intval', $wechatPayload['sent_chunks'] ?? [])));

            foreach ($chunks as $index => $chunk) {
                if (in_array($index, $sentChunks, true)) {
                    continue;
                }

                $api->sendText($channel, $openid, $chunk);

                $sentChunks[] = $index;
                $wechatPayload = array_merge($wechatPayload, [
                    'chunk_count' => count($chunks),
                    'sent_chunks' => array_values(array_unique($sentChunks)),
                ]);
                if (! $outbox->updatePayloadIfClaimed(
                    $claimToken,
                    array_merge($outboxPayload, ['wechat_oa' => $wechatPayload]),
                )) {
                    Log::warning('微信公众号出站分片保存进度时已失去 Outbox 租约。', [
                        'message_id' => $this->messageId,
                        'outbox_id' => (string) $outbox->id,
                        'chunk_index' => $index,
                    ]);
                    $this->syncMessageProjection($message, $outbox);

                    return;
                }
            }

            $sentAt = now()->toIso8601String();
            $messagePayload['wechat_oa'] = array_merge(
                $messagePayload['wechat_oa'] ?? [],
                [
                    'sent_at' => $sentAt,
                    'chunk_count' => count($chunks),
                ],
            );

            $sent = $outbox->markSentIfClaimed($claimToken, [
                'wechat_oa' => [
                    'sent_at' => $sentAt,
                    'chunk_count' => count($chunks),
                    'sent_chunks' => array_values(array_unique($sentChunks)),
                ],
            ]);

            if ($sent) {
                $message->update([
                    'delivery_status' => MessageDeliveryStatus::Sent,
                    'payload' => $messagePayload,
                ]);
                Log::info('微信公众号出站消息发送完成。', [
                    'message_id' => $this->messageId,
                    'outbox_id' => (string) $outbox->id,
                    'channel_id' => (string) $channel->id,
                    'chunk_count' => count($chunks),
                ]);
            } else {
                Log::warning('微信公众号出站完成后已失去 Outbox 租约。', [
                    'message_id' => $this->messageId,
                    'outbox_id' => (string) $outbox->id,
                ]);
                $this->syncMessageProjection($message, $outbox);
            }
        } catch (WechatApiException $e) {
            $failureReason = $this->providerFailureReason($e);
            Log::warning('微信公众号出站消息发送失败。', [
                'message_id' => $this->messageId,
                'outbox_id' => (string) $outbox->id,
                'error_code' => $e->errorCode,
                'retryable' => $e->isRetryable(),
                'reason' => $e->getMessage(),
            ]);

            if ($e->isRetryable()) {
                $outbox->releaseForRetry($claimToken, $failureReason, $this->retryDelay($outbox->attempts));
                throw $e;
            }

            $this->markClaimedFailed($message, $outbox, $claimToken, $failureReason);
        } catch (Throwable $e) {
            $outbox->releaseForRetry($claimToken, $e->getMessage(), $this->retryDelay($outbox->attempts));
            Log::warning('微信公众号出站消息发生未分类异常，已释放 Outbox 等待重试。', [
                'message_id' => $this->messageId,
                'outbox_id' => (string) $outbox->id,
                'exception' => $e::class,
                'reason' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * 上传并投递微信公众号图片，随后同步 Outbox 和消息状态。
     *
     * @param  array<string, mixed>  $messagePayload
     */
    private function sendImage(
        WechatOfficialAccountApi $api,
        ConversationMessage $message,
        MessageOutbox $outbox,
        string $claimToken,
        Channel $channel,
        string $openid,
        array $messagePayload,
    ): void {
        $attachment = Attachment::query()
            ->where('attachable_type', $message->getMorphClass())
            ->where('attachable_id', $message->getKey())
            ->first();
        if ($attachment === null) {
            Log::warning('微信公众号出站图片缺少附件。', [
                'message_id' => $this->messageId,
                'outbox_id' => (string) $outbox->id,
            ]);
            $this->markClaimedFailed($message, $outbox, $claimToken, '微信公众号出站图片缺少附件。');

            return;
        }

        $contents = $attachment->filesystem()->get($attachment->object_key);
        if (! is_string($contents)) {
            Log::warning('微信公众号出站图片无法读取存储对象。', [
                'message_id' => $this->messageId,
                'outbox_id' => (string) $outbox->id,
                'attachment_id' => (string) $attachment->id,
                'object_key' => $attachment->object_key,
            ]);
            $this->markClaimedFailed($message, $outbox, $claimToken, '微信公众号出站图片读取失败。');

            return;
        }

        $mediaId = $api->sendImage($channel, $openid, $contents, $attachment->original_name);
        $metadata = ['wechat_oa' => ['sent_at' => now()->toIso8601String(), 'media_id' => $mediaId]];
        if ($outbox->markSentIfClaimed($claimToken, $metadata, $mediaId)) {
            $message->update([
                'delivery_status' => MessageDeliveryStatus::Sent,
                'payload' => array_merge($messagePayload, $metadata),
            ]);

            return;
        }

        $this->syncMessageProjection($message, $outbox);
    }

    /** 将耗尽重试的任务同步为投递失败。 */
    public function failed(Throwable $exception): void
    {
        $outbox = MessageOutbox::query()
            ->where('conversation_message_id', $this->messageId)
            ->first();
        $outbox?->failIfUnsent($exception->getMessage());

        $message = ConversationMessage::query()->find($this->messageId);
        if ($message !== null && $outbox !== null) {
            $this->syncMessageProjection($message, $outbox);
        }

        Log::warning('微信公众号出站任务已耗尽重试。', [
            'message_id' => $this->messageId,
            'outbox_id' => $outbox === null ? null : (string) $outbox->id,
            'reason' => $exception->getMessage(),
        ]);
    }

    /** 将当前租约标记为失败并同步消息状态。 */
    private function markClaimedFailed(
        ConversationMessage $message,
        MessageOutbox $outbox,
        string $claimToken,
        string $reason,
    ): void {
        if ($outbox->markFailedIfClaimed($claimToken, $reason)) {
            $message->update(['delivery_status' => MessageDeliveryStatus::Failed]);

            return;
        }

        $this->syncMessageProjection($message, $outbox);
    }

    /** 根据 Outbox 同步会话消息的投递状态。 */
    private function syncMessageProjection(ConversationMessage $message, MessageOutbox $outbox): void
    {
        $status = match ($outbox->status) {
            MessageOutboxStatus::Sent => MessageDeliveryStatus::Sent,
            MessageOutboxStatus::Failed => MessageDeliveryStatus::Failed,
            default => MessageDeliveryStatus::Sending,
        };

        if ($message->delivery_status !== $status) {
            $message->update(['delivery_status' => $status]);
        }
    }

    /** 生成包含微信错误码的失败原因。 */
    private function providerFailureReason(WechatApiException $exception): string
    {
        if ($exception->errorCode === 0) {
            return $exception->getMessage();
        }

        return sprintf('[微信错误码 %d] %s', $exception->errorCode, $exception->getMessage());
    }

    /** 按当前投递次数返回与队列一致的重试等待秒数。 */
    private function retryDelay(int $attempts): int
    {
        return $attempts <= 1 ? 5 : 30;
    }
}
