<?php

namespace App\Actions\Channel\WechatOfficialAccount;

use App\Enums\ChannelType;
use App\Jobs\WechatOfficialAccount\ProcessWechatOfficialAccountMessageJob;
use App\Models\Channel;
use App\Models\WechatInboundMessage;
use App\Services\Wechat\WechatOfficialAccountWebhookService;
use EasyWeChat\Kernel\Exceptions\BadRequestException;
use EasyWeChat\Kernel\Exceptions\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/** 验证微信公众号 webhook 并派发入站消息。 */
class ReceiveWechatOfficialAccountWebhookAction
{
    use AsAction;

    /** 创建微信公众号 webhook 入口。 */
    public function __construct(
        private readonly WechatOfficialAccountWebhookService $webhook,
    ) {}

    /** 处理服务器验证或入站消息回调。 */
    public function asController(Request $request, string $code): Response
    {
        $channel = $this->findChannel($code);

        try {
            if ($request->isMethod('GET')) {
                $echo = $this->webhook->verifyUrl($channel, $request);

                return response($echo, 200, [
                    'Content-Type' => 'text/plain; charset=UTF-8',
                ]);
            }

            $payload = $this->webhook->parseMessage($channel, $request);
            $providerMessageId = $this->providerMessageId($payload);
            $inbound = WechatInboundMessage::query()->firstOrCreate(
                [
                    'channel_id' => $channel->id,
                    'provider_message_id' => $providerMessageId,
                ],
                [
                    'message_type' => strtolower((string) ($payload['MsgType'] ?? 'unknown')),
                    'payload' => $payload,
                    'available_at' => now(),
                ],
            );
            if ($inbound->wasRecentlyCreated) {
                ProcessWechatOfficialAccountMessageJob::dispatch((string) $inbound->id)->afterCommit();
            }

            return response('success', 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        } catch (InvalidArgumentException|BadRequestException|DecryptException $e) {
            Log::warning('微信公众号 webhook 验证失败。', [
                'channel_code' => $code,
                'exception' => $e::class,
                'reason' => $e->getMessage(),
            ]);

            return response('invalid', 403, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }
    }

    /** 为缺少 MsgId 的回调生成稳定去重标识。 */
    private function providerMessageId(array $payload): string
    {
        $messageId = $payload['MsgId'] ?? null;
        if (is_scalar($messageId) && trim((string) $messageId) !== '') {
            return (string) $messageId;
        }

        return 'event_'.hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** 按公开代码查找微信公众号渠道。 */
    private function findChannel(string $code): Channel
    {
        $channel = Channel::query()
            ->where('code', $code)
            ->where('type', ChannelType::WechatOfficialAccount)
            ->first();

        if ($channel === null) {
            throw new NotFoundHttpException;
        }

        return $channel;
    }
}
