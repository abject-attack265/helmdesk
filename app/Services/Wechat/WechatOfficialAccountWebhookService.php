<?php

namespace App\Services\Wechat;

use App\Data\Channel\WechatOfficialAccount\ChannelWechatOfficialAccountSettingsData;
use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** 验证并解析微信公众号回调。 */
class WechatOfficialAccountWebhookService
{
    /** 创建微信公众号回调服务。 */
    public function __construct(
        private readonly WechatOfficialAccountApplicationFactory $applications,
    ) {}

    /** 验证公众平台服务器配置请求并返回回显内容。 */
    public function verifyUrl(Channel $channel, Request $request): string
    {
        $settings = $this->settings($channel);
        $this->validatePlainSignature($settings->token, $request);

        $echoString = (string) $request->query('echostr', '');
        if ($echoString === '') {
            throw new InvalidArgumentException('微信公众号回调验证缺少 echostr。');
        }

        return $echoString;
    }

    /**
     * 验证并解析微信公众号事件消息。
     *
     * @return array<string, mixed>
     */
    public function parseMessage(Channel $channel, Request $request): array
    {
        $settings = $this->settings($channel);
        $application = $this->applications->make($channel);
        $application->setRequestFromSymfonyRequest($request);

        if ($settings->usesEncryption()) {
            if ((string) $request->query('msg_signature', '') === '') {
                throw new InvalidArgumentException('微信公众号安全模式回调缺少 msg_signature。');
            }

            return $application->getServer()->getDecryptedMessage()->toArray();
        }

        $this->validatePlainSignature($settings->token, $request);

        return $application->getServer()->getRequestMessage()->toArray();
    }

    /** 获取完成必要配置的微信公众号渠道设置。 */
    private function settings(Channel $channel): ChannelWechatOfficialAccountSettingsData
    {
        if ($channel->type !== ChannelType::WechatOfficialAccount) {
            throw new InvalidArgumentException('当前渠道不是微信公众号渠道。');
        }

        /** @var ChannelWechatOfficialAccountSettingsData $settings */
        $settings = $channel->settings;
        if (! $settings->isConfigured()) {
            throw new InvalidArgumentException('微信公众号渠道尚未完成必要配置。');
        }

        return $settings;
    }

    /** 验证微信公众号明文模式签名。 */
    private function validatePlainSignature(string $token, Request $request): void
    {
        $signature = (string) $request->query('signature', '');
        $parts = [$token, (string) $request->query('timestamp', ''), (string) $request->query('nonce', '')];
        sort($parts, SORT_STRING);

        if ($signature === '' || ! hash_equals(sha1(implode('', $parts)), $signature)) {
            throw new InvalidArgumentException('微信公众号回调签名无效。');
        }
    }
}
