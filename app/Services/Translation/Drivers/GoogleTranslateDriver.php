<?php

namespace App\Services\Translation\Drivers;

use App\Services\Translation\TranslationResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * 通过 API Key 调用 Google Translation v2。
 */
class GoogleTranslateDriver extends HttpTranslationDriver
{
    private const string ENDPOINT = 'https://translation.googleapis.com/language/translate/v2';

    /**
     * 调用 Google Translation v2 并返回统一翻译结果。
     *
     * @param  array<string, mixed>  $options
     */
    public function translate(string $text, string $sourceLang, string $targetLang, array $options = []): TranslationResult
    {
        $apiKey = $this->requiredCredential('api_key');

        $payload = [
            'q' => $text,
            'target' => $targetLang,
            'format' => 'text',
        ];

        // source 为空或 auto 时由 Google 自动识别源语言。
        if ($sourceLang !== '' && $sourceLang !== 'auto') {
            $payload['source'] = $sourceLang;
        }

        $startedAt = $this->nowMs();

        $response = $this->sendRequest(fn (): Response => Http::timeout($this->requestTimeout())
            ->retry(2, 200, throw: false)
            ->asForm()
            ->post(self::ENDPOINT.'?key='.urlencode($apiKey), $payload));

        $body = $response->json();
        $first = $body['data']['translations'][0] ?? null;
        if (! is_array($first) || ! isset($first['translatedText'])) {
            throw $this->missingTranslationsPayload($response->status());
        }

        // Google v2 的文本响应仍可能包含 HTML 实体。
        return new TranslationResult(
            text: html_entity_decode((string) $first['translatedText'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            source_lang: (string) ($first['detectedSourceLanguage'] ?? $sourceLang),
            target_lang: $targetLang,
            provider_slug: $this->provider->slug,
            model: null,
            latency_ms: $this->latencyMs($startedAt),
            char_count: mb_strlen($text),
        );
    }

    /**
     * 从 Google 错误响应体中读取错误信息。
     */
    protected function extractErrorMessage(Response $response): ?string
    {
        $body = $response->json();
        $message = is_array($body) ? ($body['error']['message'] ?? null) : null;

        return is_string($message) ? $message : null;
    }
}
