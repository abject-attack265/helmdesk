<?php

namespace App\Services\Translation\Drivers;

use App\Services\Translation\TranslationResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * 调用百度翻译开放平台通用文本翻译 API。
 */
class BaiduTranslateDriver extends HttpTranslationDriver
{
    /**
     * 调用百度通用文本翻译 API。
     *
     * @param  array<string, mixed>  $options
     */
    public function translate(string $text, string $sourceLang, string $targetLang, array $options = []): TranslationResult
    {
        $appId = $this->requiredCredential('app_id');
        $appSecret = $this->requiredCredential('app_secret');
        $endpoint = $this->credentialOrDefault('endpoint', 'https://fanyi-api.baidu.com/api/trans/vip/translate');
        $salt = (string) Str::uuid();
        $from = $this->normalizeBaiduLanguage($this->normalizeSourceLang($sourceLang), source: true);
        $to = $this->normalizeBaiduLanguage($targetLang, source: false);

        $payload = [
            'q' => $text,
            'from' => $from,
            'to' => $to,
            'appid' => $appId,
            'salt' => $salt,
            'sign' => md5($appId.$text.$salt.$appSecret),
        ];

        $startedAt = $this->nowMs();

        $response = $this->sendRequest(fn (): Response => Http::timeout($this->requestTimeout())
            ->retry(2, 200, throw: false)
            ->asForm()
            ->post($endpoint, $payload));

        // 业务错误通过 200 响应体中的 error_code 返回。
        $body = $response->json();
        if (is_array($body) && isset($body['error_code'])) {
            throw $this->upstreamFailed($response, $this->extractErrorMessage($response));
        }

        $first = is_array($body) ? ($body['trans_result'][0] ?? null) : null;
        if (! is_array($first) || ! isset($first['dst'])) {
            throw $this->missingTranslationsPayload($response->status());
        }

        return new TranslationResult(
            text: html_entity_decode((string) $first['dst'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            source_lang: is_array($body) && isset($body['from']) ? (string) $body['from'] : $sourceLang,
            target_lang: $targetLang,
            provider_slug: $this->provider->slug,
            model: null,
            latency_ms: $this->latencyMs($startedAt),
            char_count: mb_strlen($text),
        );
    }

    /**
     * 将通用语言标签转换为百度翻译语言代码。
     */
    private function normalizeBaiduLanguage(string $language, bool $source): string
    {
        $normalized = strtolower(str_replace('_', '-', trim($language)));

        if ($source && $normalized === 'auto') {
            return 'auto';
        }

        return match ($normalized) {
            'zh', 'zh-cn', 'zh-hans' => 'zh',
            'zh-tw', 'zh-hant', 'zh-hk' => 'cht',
            'ja', 'ja-jp' => 'jp',
            'ko', 'ko-kr' => 'kor',
            default => explode('-', $normalized)[0],
        };
    }

    /**
     * 从百度翻译错误响应体中提取错误信息。
     */
    protected function extractErrorMessage(Response $response): ?string
    {
        $body = $response->json();
        if (! is_array($body)) {
            return null;
        }

        $message = $body['error_msg'] ?? $body['error_code'] ?? null;

        return is_scalar($message) ? (string) $message : null;
    }
}
