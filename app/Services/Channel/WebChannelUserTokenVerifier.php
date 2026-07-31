<?php

namespace App\Services\Channel;

use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Models\Channel;
use Illuminate\Support\Facades\Log;
use JsonException;

/**
 * 校验网站渠道使用 HS256 签发的访客身份 token。
 *
 * 格式、签名和 claim 异常记录 warning；时效异常记录 debug。校验失败返回 null，
 * 由接待入口继续使用会话身份。
 */
class WebChannelUserTokenVerifier
{
    /**
     * JWT 时间字段允许的时钟漂移（秒）。
     */
    private const int LEEWAY_SECONDS = 60;

    /**
     * token 时间字段相关的失败原因：属正常生命周期/时钟漂移，按 debug 记而非 warn。
     */
    private const array LIFECYCLE_REASONS = ['expired', 'not_yet_valid', 'iat_future'];

    /**
     * 校验签名 token 并返回标准化身份字段。
     *
     * 返回结构：
     *  - external_id : sub claim，必填
     *  - name        : 可选展示名
     *  - email       : 可选邮箱
     *  - claims      : 原始 payload，便于后续扩展
     *
     * @return array{external_id: string, name: ?string, email: ?string, claims: array<string, mixed>}|null
     */
    public function verify(Channel $channel, ?string $token): ?array
    {
        return $this->parse($channel, $token, enforceLifecycle: true);
    }

    /**
     * 校验签名与 claim 但放宽时效（exp/nbf/iat）。
     *
     * 仅用于「按签名身份定位既有资源」的收尾类操作（如对已关闭会话补交评价）：过期只意味着不能再
     * 新建会话，不应阻断收尾。签名仍必须有效，故不可伪造。
     *
     * @return array{external_id: string, name: ?string, email: ?string, claims: array<string, mixed>}|null
     */
    public function verifyIgnoringLifecycle(Channel $channel, ?string $token): ?array
    {
        return $this->parse($channel, $token, enforceLifecycle: false);
    }

    /**
     * 解析并校验签名 token。$enforceLifecycle 为真时额外校验 exp/nbf/iat 时效。
     *
     * @return array{external_id: string, name: ?string, email: ?string, claims: array<string, mixed>}|null
     */
    private function parse(Channel $channel, ?string $token, bool $enforceLifecycle): ?array
    {
        /** @var ChannelWebSettingsData $settings */
        $settings = $channel->settings;
        $secret = trim((string) ($settings->user_token_secret ?? ''));
        $token = trim((string) $token);
        if ($token === '') {
            return null;
        }
        if ($secret === '') {
            return $this->logAndFail('missing_secret', $channel);
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return $this->logAndFail('format', $channel);
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;
        $header = $this->decodeJson($headerB64);
        $payload = $this->decodeJson($payloadB64);
        if (! is_array($header) || ! is_array($payload)) {
            return $this->logAndFail('decode', $channel);
        }

        if (($header['alg'] ?? null) !== 'HS256') {
            return $this->logAndFail('alg', $channel);
        }
        if (($header['typ'] ?? 'JWT') !== 'JWT') {
            return $this->logAndFail('typ', $channel);
        }

        $expected = hash_hmac('sha256', $headerB64.'.'.$payloadB64, $secret, true);
        $actual = $this->base64UrlDecode($signatureB64);
        if ($actual === null || ! hash_equals($expected, $actual)) {
            return $this->logAndFail('signature', $channel);
        }

        if ($enforceLifecycle) {
            $now = time();
            if (isset($payload['exp'])) {
                if (! is_numeric($payload['exp'])) {
                    return $this->logAndFail('invalid_exp', $channel);
                }
                if ($now > ((int) $payload['exp']) + self::LEEWAY_SECONDS) {
                    return $this->logAndFail('expired', $channel);
                }
            }
            if (isset($payload['nbf'])) {
                if (! is_numeric($payload['nbf'])) {
                    return $this->logAndFail('invalid_nbf', $channel);
                }
                if ($now + self::LEEWAY_SECONDS < (int) $payload['nbf']) {
                    return $this->logAndFail('not_yet_valid', $channel);
                }
            }
            if (isset($payload['iat'])) {
                if (! is_numeric($payload['iat'])) {
                    return $this->logAndFail('invalid_iat', $channel);
                }
                if ((int) $payload['iat'] > $now + self::LEEWAY_SECONDS) {
                    return $this->logAndFail('iat_future', $channel);
                }
            }
        }

        $externalId = $this->stringClaim($payload, 'sub');
        if ($externalId === '') {
            return $this->logAndFail('missing_sub', $channel);
        }
        if (strlen($externalId) > 191) {
            return $this->logAndFail('sub_too_long', $channel);
        }

        $emailClaim = $payload['email'] ?? null;
        $email = is_string($emailClaim) ? trim($emailClaim) : '';
        if ($emailClaim !== null && ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL))) {
            Log::warning('网站渠道 user_token 的 email claim 无效', [
                'channel_id' => $channel->id,
                'channel_code' => $channel->code,
            ]);
            $email = '';
        }

        return [
            'external_id' => $externalId,
            'name' => $this->stringClaim($payload, 'name') ?: null,
            'email' => $email !== '' ? strtolower($email) : null,
            'claims' => $payload,
        ];
    }

    /**
     * 解码 JWT 的 Base64URL JSON 分段。
     *
     * @return array<string, mixed>|null
     */
    private function decodeJson(string $value): ?array
    {
        $decoded = $this->base64UrlDecode($value);
        if (! is_string($decoded)) {
            return null;
        }

        try {
            $object = json_decode($decoded, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($object) ? $object : null;
    }

    /**
     * 严格解码 Base64URL 字符串，非法输入返回 null。
     */
    private function base64UrlDecode(string $value): ?string
    {
        $padded = strtr($value, '-_', '+/');
        $remainder = strlen($padded) % 4;
        if ($remainder > 0) {
            $padded .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode($padded, true);

        return $decoded === false ? null : $decoded;
    }

    /**
     * 读取 payload 中指定 claim 并 trim 为字符串；缺失或非字符串返回空串。
     *
     * @param  array<string, mixed>  $payload
     */
    private function stringClaim(array $payload, string $key): string
    {
        $value = $payload[$key] ?? '';
        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }

    /**
     * 记录 token 校验失败原因并返回 null。
     */
    private function logAndFail(string $reason, Channel $channel): ?array
    {
        $context = [
            'channel_id' => $channel->id,
            'channel_code' => $channel->code,
            'reason' => $reason,
        ];

        if (in_array($reason, self::LIFECYCLE_REASONS, true)) {
            Log::debug('网站渠道 user_token 时效校验未通过', $context);
        } else {
            Log::warning('网站渠道 user_token 校验失败', $context);
        }

        return null;
    }
}
