<?php

namespace App\Services\AiRuntime;

use GuzzleHttp\RequestOptions;
use NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAI\HttpClient\HttpClientInterface;
use RuntimeException;

/**
 * 为 AI Provider 创建带平台 TLS 策略的 HTTP Client。
 *
 * Windows 版 PHP 的 cURL 使用 OpenSSL，默认不会读取 Windows 系统证书库；启用原生 CA 后，
 * 企业代理等已受系统信任的证书可以继续通过严格校验。其它平台沿用 cURL 默认 CA 配置。
 */
class AiHttpClientFactory
{
    /**
     * 创建供单个 AI Provider 独占的 HTTP Client。
     */
    public function make(): HttpClientInterface
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return new GuzzleHttpClient;
        }

        if (! defined('CURLSSLOPT_NATIVE_CA')) {
            throw new RuntimeException('Windows AI HTTP 客户端需要 cURL 原生 CA 支持。');
        }

        return new GuzzleHttpClient(options: [
            RequestOptions::CURL => [
                CURLOPT_SSL_OPTIONS => (int) constant('CURLSSLOPT_NATIVE_CA'),
            ],
        ]);
    }
}
