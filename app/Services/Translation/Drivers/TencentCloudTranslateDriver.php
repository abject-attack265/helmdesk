<?php

namespace App\Services\Translation\Drivers;

use App\Services\Translation\TranslationResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * 调用腾讯云机器翻译 TextTranslate API。
 */
class TencentCloudTranslateDriver extends HttpTranslationDriver
{
    private const string ACTION = 'TextTranslate';

    private const string SERVICE = 'tmt';

    private const string VERSION = '2018-03-21';

    /**
     * 调用腾讯云机器翻译 TextTranslate 接口。
     *
     * @param  array<string, mixed>  $options
     */
    public function translate(string $text, string $sourceLang, string $targetLang, array $options = []): TranslationResult
    {
        $secretId = $this->requiredCredential('secret_id');
        $secretKey = $this->requiredCredential('secret_key');
        $region = $this->credentialOrDefault('region', 'ap-guangzhou');
        $endpoint = $this->credentialOrDefault('endpoint', 'https://tmt.tencentcloudapi.com');
        $host = (string) parse_url($endpoint, PHP_URL_HOST);
        $timestamp = time();

        $payload = json_encode([
            'SourceText' => $text,
            'Source' => $this->normalizeTencentLanguage($this->normalizeSourceLang($sourceLang), source: true),
            'Target' => $this->normalizeTencentLanguage($targetLang, source: false),
            'ProjectId' => 0,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($payload)) {
            throw $this->missingTranslationsPayload();
        }

        $headers = [
            'Authorization' => $this->buildAuthorization($secretId, $secretKey, $host, $payload, $timestamp),
            'Content-Type' => 'application/json; charset=utf-8',
            'Host' => $host,
            'X-TC-Action' => self::ACTION,
            'X-TC-Region' => $region,
            'X-TC-Timestamp' => (string) $timestamp,
            'X-TC-Version' => self::VERSION,
        ];

        $startedAt = $this->nowMs();

        $response = $this->sendRequest(fn (): Response => Http::timeout($this->requestTimeout())
            ->retry(2, 200, throw: false)
            ->withHeaders($headers)
            ->withBody($payload, 'application/json; charset=utf-8')
            ->post($endpoint));

        // 业务错误通过 200 响应体中的 Response.Error 返回。
        $body = $response->json();
        $responseBody = is_array($body) ? ($body['Response'] ?? null) : null;
        if (is_array($responseBody) && isset($responseBody['Error'])) {
            throw $this->upstreamFailed($response, $this->extractErrorMessage($response));
        }

        if (! is_array($responseBody) || ! isset($responseBody['TargetText'])) {
            throw $this->missingTranslationsPayload($response->status());
        }

        return new TranslationResult(
            text: (string) $responseBody['TargetText'],
            source_lang: isset($responseBody['Source']) ? (string) $responseBody['Source'] : $sourceLang,
            target_lang: $targetLang,
            provider_slug: $this->provider->slug,
            model: null,
            latency_ms: $this->latencyMs($startedAt),
            char_count: mb_strlen($text),
        );
    }

    /**
     * 将通用语言标签转换为腾讯云机器翻译语言代码。
     */
    private function normalizeTencentLanguage(string $language, bool $source): string
    {
        $normalized = strtolower(str_replace('_', '-', trim($language)));

        if ($source && $normalized === 'auto') {
            return 'auto';
        }

        return match ($normalized) {
            'zh', 'zh-cn', 'zh-hans' => 'zh',
            'zh-tw', 'zh-hant', 'zh-hk' => 'zh-TW',
            'ja', 'ja-jp' => 'ja',
            'ko', 'ko-kr' => 'ko',
            default => explode('-', $normalized)[0],
        };
    }

    /**
     * 按腾讯云 TC3-HMAC-SHA256 规范生成 Authorization 请求头。
     */
    private function buildAuthorization(string $secretId, string $secretKey, string $host, string $payload, int $timestamp): string
    {
        $date = gmdate('Y-m-d', $timestamp);
        $canonicalHeaders = "content-type:application/json; charset=utf-8\nhost:{$host}\n";
        $signedHeaders = 'content-type;host';
        $canonicalRequest = implode("\n", [
            'POST',
            '/',
            '',
            $canonicalHeaders,
            $signedHeaders,
            hash('sha256', $payload),
        ]);

        $credentialScope = "{$date}/".self::SERVICE.'/tc3_request';
        $stringToSign = implode("\n", [
            'TC3-HMAC-SHA256',
            (string) $timestamp,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $secretDate = hash_hmac('sha256', $date, 'TC3'.$secretKey, binary: true);
        $secretService = hash_hmac('sha256', self::SERVICE, $secretDate, binary: true);
        $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, binary: true);
        $signature = hash_hmac('sha256', $stringToSign, $secretSigning);

        return 'TC3-HMAC-SHA256 Credential='.$secretId.'/'.$credentialScope.', SignedHeaders='.$signedHeaders.', Signature='.$signature;
    }

    /**
     * 从腾讯云机器翻译错误响应体中提取错误信息。
     */
    protected function extractErrorMessage(Response $response): ?string
    {
        $body = $response->json();
        $message = is_array($body) ? ($body['Response']['Error']['Message'] ?? null) : null;

        return is_string($message) ? $message : null;
    }
}
