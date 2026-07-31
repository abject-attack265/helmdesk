<?php

namespace App\Services\Translation\Drivers;

use App\Services\Translation\TranslationResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * 通过 AWS JSON API 和 Signature V4 调用 Amazon Translate。
 */
class AmazonTranslateDriver extends HttpTranslationDriver
{
    private const string SERVICE = 'translate';

    private const string TARGET = 'AWSShineFrontendService_20170701.TranslateText';

    /**
     * 调用 Amazon Translate TranslateText 接口。
     *
     * @param  array<string, mixed>  $options
     */
    public function translate(string $text, string $sourceLang, string $targetLang, array $options = []): TranslationResult
    {
        $accessKeyId = $this->requiredCredential('access_key_id');
        $secretAccessKey = $this->requiredCredential('secret_access_key');
        $region = $this->credentialOrDefault('region', 'us-east-1');
        $endpoint = $this->credential('endpoint') ?: "https://translate.{$region}.amazonaws.com";
        $host = (string) parse_url($endpoint, PHP_URL_HOST);
        $amzDate = gmdate('Ymd\THis\Z');
        $date = substr($amzDate, 0, 8);

        $payload = json_encode([
            'Text' => $text,
            'SourceLanguageCode' => $this->normalizeAwsLanguage($this->normalizeSourceLang($sourceLang), source: true),
            'TargetLanguageCode' => $this->normalizeAwsLanguage($targetLang, source: false),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($payload)) {
            throw $this->missingTranslationsPayload();
        }

        $sessionToken = $this->credential('session_token');
        $hasSessionToken = $sessionToken !== '';

        $headers = [
            'Authorization' => $this->buildAuthorization($accessKeyId, $secretAccessKey, $region, $host, $payload, $amzDate, $date, hasSessionToken: $hasSessionToken),
            'Content-Type' => 'application/x-amz-json-1.1',
            'Host' => $host,
            'X-Amz-Date' => $amzDate,
            'X-Amz-Target' => self::TARGET,
        ];

        if ($hasSessionToken) {
            $headers['X-Amz-Security-Token'] = $sessionToken;
        }

        $startedAt = $this->nowMs();

        $response = $this->sendRequest(fn (): Response => Http::timeout($this->requestTimeout())
            ->retry(2, 200, throw: false)
            ->withHeaders($headers)
            ->withBody($payload, 'application/x-amz-json-1.1')
            ->post($endpoint));

        $body = $response->json();
        if (! is_array($body) || ! isset($body['TranslatedText'])) {
            throw $this->missingTranslationsPayload($response->status());
        }

        return new TranslationResult(
            text: (string) $body['TranslatedText'],
            source_lang: isset($body['SourceLanguageCode']) ? (string) $body['SourceLanguageCode'] : $sourceLang,
            target_lang: $targetLang,
            provider_slug: $this->provider->slug,
            model: null,
            latency_ms: $this->latencyMs($startedAt),
            char_count: mb_strlen($text),
        );
    }

    /**
     * 将通用语言标签转换为 Amazon Translate 语言代码。
     */
    private function normalizeAwsLanguage(string $language, bool $source): string
    {
        $normalized = strtolower(str_replace('_', '-', trim($language)));

        if ($source && $normalized === 'auto') {
            return 'auto';
        }

        return match ($normalized) {
            'zh', 'zh-cn', 'zh-hans' => 'zh',
            'zh-tw', 'zh-hant', 'zh-hk' => 'zh-TW',
            default => $normalized,
        };
    }

    /**
     * 按 AWS Signature V4 规范生成 Authorization 请求头。
     */
    private function buildAuthorization(
        string $accessKeyId,
        string $secretAccessKey,
        string $region,
        string $host,
        string $payload,
        string $amzDate,
        string $date,
        bool $hasSessionToken = false,
    ): string {
        $canonicalHeaders = "content-type:application/x-amz-json-1.1\nhost:{$host}\nx-amz-date:{$amzDate}\nx-amz-target:".self::TARGET."\n";
        $signedHeaders = 'content-type;host;x-amz-date;x-amz-target';

        if ($hasSessionToken) {
            $canonicalHeaders = "content-type:application/x-amz-json-1.1\nhost:{$host}\nx-amz-date:{$amzDate}\nx-amz-security-token:".$this->credential('session_token')."\nx-amz-target:".self::TARGET."\n";
            $signedHeaders = 'content-type;host;x-amz-date;x-amz-security-token;x-amz-target';
        }

        $canonicalRequest = implode("\n", [
            'POST',
            '/',
            '',
            $canonicalHeaders,
            $signedHeaders,
            hash('sha256', $payload),
        ]);

        $credentialScope = "{$date}/{$region}/".self::SERVICE.'/aws4_request';
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $amzDate,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $dateKey = hash_hmac('sha256', $date, 'AWS4'.$secretAccessKey, binary: true);
        $dateRegionKey = hash_hmac('sha256', $region, $dateKey, binary: true);
        $dateRegionServiceKey = hash_hmac('sha256', self::SERVICE, $dateRegionKey, binary: true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $dateRegionServiceKey, binary: true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        return 'AWS4-HMAC-SHA256 Credential='.$accessKeyId.'/'.$credentialScope.', SignedHeaders='.$signedHeaders.', Signature='.$signature;
    }

    /**
     * 从 Amazon Translate 错误响应体中提取错误信息。
     */
    protected function extractErrorMessage(Response $response): ?string
    {
        $body = $response->json();
        if (! is_array($body)) {
            return null;
        }

        $message = $body['message'] ?? $body['Message'] ?? $body['__type'] ?? null;

        return is_scalar($message) ? (string) $message : null;
    }
}
