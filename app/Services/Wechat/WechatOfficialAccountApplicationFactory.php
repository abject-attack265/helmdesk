<?php

namespace App\Services\Wechat;

use App\Data\Channel\WechatOfficialAccount\ChannelWechatOfficialAccountSettingsData;
use App\Enums\ChannelType;
use App\Models\Channel;
use EasyWeChat\OfficialAccount\Application;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/** 创建微信公众号应用实例。 */
class WechatOfficialAccountApplicationFactory
{
    /** 使用渠道配置创建微信公众号应用实例。 */
    public function make(Channel $channel): Application
    {
        if ($channel->type !== ChannelType::WechatOfficialAccount) {
            throw new InvalidArgumentException('当前渠道不是微信公众号渠道。');
        }

        /** @var ChannelWechatOfficialAccountSettingsData $settings */
        $settings = $channel->settings;
        if (! $settings->isConfigured()) {
            throw new InvalidArgumentException('微信公众号渠道配置无效。');
        }

        $application = new Application([
            'app_id' => $settings->app_id,
            'secret' => $settings->app_secret,
            'token' => $settings->token,
            'aes_key' => $settings->aes_key,
            'http' => ['timeout' => 10, 'throw' => true],
        ]);

        /** @var CacheRepository $cache */
        $cache = Cache::store();
        $application->setCache($cache);
        $application->setAccessToken(new WechatAccessToken(
            appId: $settings->app_id,
            secret: $settings->app_secret,
            cache: $cache,
            httpClient: $application->getHttpClient(),
        ));

        return $application;
    }
}
