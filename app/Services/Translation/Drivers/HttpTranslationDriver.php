<?php

namespace App\Services\Translation\Drivers;

use App\Models\TranslationProvider;
use App\Services\Translation\Exceptions\TranslationProviderException;
use App\Services\Translation\TranslatorContract;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

/**
 * HTTP 翻译驱动基类，集中处理凭据、耗时和上游异常。
 */
abstract class HttpTranslationDriver implements TranslatorContract
{
    /**
     * 注入承载凭据和标识的翻译供应商。
     */
    public function __construct(protected readonly TranslationProvider $provider) {}

    /**
     * 执行一次上游 HTTP 请求，统一包装连接失败和错误响应。
     *
     * @param  \Closure(): Response  $send
     */
    protected function sendRequest(\Closure $send): Response
    {
        try {
            $response = $send();
        } catch (ConnectionException $exception) {
            throw $this->connectionFailed($exception);
        }

        if ($response->failed()) {
            throw $this->upstreamFailed($response, $this->extractErrorMessage($response));
        }

        return $response;
    }

    /**
     * 从上游错误响应中提取错误信息。
     */
    protected function extractErrorMessage(Response $response): ?string
    {
        return null;
    }

    /**
     * 读取必填凭据，空值抛出供应商异常。
     */
    protected function requiredCredential(string $field): string
    {
        $value = $this->credential($field);

        if ($value === '') {
            throw new TranslationProviderException(
                __('translation.driver_errors.missing_credential', [
                    'provider' => $this->provider->name,
                    'field' => $field,
                ]),
                providerSlug: $this->provider->slug,
            );
        }

        return $value;
    }

    /**
     * 读取可选凭据，缺失时返回空字符串。
     */
    protected function credential(string $field): string
    {
        $credentials = $this->provider->credentials ?? [];
        $value = $credentials[$field] ?? '';

        return trim((string) $value);
    }

    /**
     * 读取可选凭据，缺失时返回默认值。
     */
    protected function credentialOrDefault(string $field, string $default): string
    {
        $value = $this->credential($field);

        return $value !== '' ? $value : $default;
    }

    /**
     * 返回统一 HTTP 超时时间。
     */
    protected function requestTimeout(): int
    {
        return (int) config('translation.request_timeout', 5);
    }

    /**
     * 当前毫秒时间戳，用于耗时统计。
     */
    protected function nowMs(): int
    {
        return (int) round(microtime(true) * 1000);
    }

    /**
     * 计算从 startedAt 到当前的耗时毫秒数。
     */
    protected function latencyMs(int $startedAt): int
    {
        return $this->nowMs() - $startedAt;
    }

    /**
     * 包装网络连接失败。
     */
    protected function connectionFailed(ConnectionException $exception): TranslationProviderException
    {
        return new TranslationProviderException(
            __('translation.driver_errors.connection_failed', [
                'provider' => $this->provider->name,
                'message' => $exception->getMessage(),
            ]),
            providerSlug: $this->provider->slug,
            previous: $exception,
        );
    }

    /**
     * 包装上游 HTTP 错误响应。
     */
    protected function upstreamFailed(Response $response, ?string $message = null, ?\Throwable $previous = null): TranslationProviderException
    {
        return new TranslationProviderException(
            __('translation.driver_errors.upstream_error', [
                'provider' => $this->provider->name,
                'message' => $message ?? $response->body(),
            ]),
            statusCode: $response->status(),
            providerSlug: $this->provider->slug,
            previous: $previous,
        );
    }

    /**
     * 包装上游成功响应形状异常。
     */
    protected function missingTranslationsPayload(?int $statusCode = null): TranslationProviderException
    {
        return new TranslationProviderException(
            __('translation.driver_errors.missing_translations_payload', [
                'provider' => $this->provider->name,
            ]),
            statusCode: $statusCode,
            providerSlug: $this->provider->slug,
        );
    }

    /**
     * 将空源语言转换为 auto。
     */
    protected function normalizeSourceLang(string $sourceLang): string
    {
        $sourceLang = trim($sourceLang);

        return $sourceLang !== '' ? $sourceLang : 'auto';
    }
}
