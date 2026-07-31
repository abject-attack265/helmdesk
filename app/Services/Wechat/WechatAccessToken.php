<?php

namespace App\Services\Wechat;

use App\Exceptions\WechatApiException;
use EasyWeChat\OfficialAccount\AccessToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/** 缓存并串行刷新微信公众号 access_token。 */
class WechatAccessToken extends AccessToken
{
    private const int CACHE_TTL_SECONDS = 7000;

    /** 请求并缓存微信公众号 access_token。 */
    public function getAccessToken(): string
    {
        $result = $this->httpClient->request(
            'GET',
            'cgi-bin/token',
            [
                'query' => [
                    'grant_type' => 'client_credential',
                    'appid' => $this->appId,
                    'secret' => $this->secret,
                ],
            ],
        )->toArray(false);

        $token = $result['access_token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw WechatApiException::fromResult($result, '微信公众号 access_token 获取失败。');
        }

        $this->cache->set($this->getKey(), $token, (int) $result['expires_in']);

        return $token;
    }

    /** 获取缓存中的 access_token，必要时加锁刷新。 */
    public function getToken(): string
    {
        $token = $this->cache->get($this->getKey());
        if (is_string($token) && $token !== '') {
            return $token;
        }

        try {
            return Cache::lock('wechat:access-token:'.sha1($this->getKey()), 15)->block(5, function (): string {
                $token = $this->cache->get($this->getKey());

                return is_string($token) && $token !== '' ? $token : $this->refresh();
            });
        } catch (Throwable $e) {
            Log::warning('微信公众号 access_token 获取失败。', [
                'credential_key' => sha1($this->getKey()),
                'exception' => $e::class,
                'reason' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /** 刷新并缓存微信公众号 access_token。 */
    public function refresh(): string
    {
        $token = parent::refresh();
        $this->cache->set($this->getKey(), $token, self::CACHE_TTL_SECONDS);

        return $token;
    }
}
