<?php

namespace App\Services\Translation;

use App\Enums\TranslationProviderType;
use App\Models\TranslationProvider;
use App\Services\Translation\Drivers\AmazonTranslateDriver;
use App\Services\Translation\Drivers\AzureTranslatorDriver;
use App\Services\Translation\Drivers\BaiduTranslateDriver;
use App\Services\Translation\Drivers\DeepLDriver;
use App\Services\Translation\Drivers\DeepSeekTranslateDriver;
use App\Services\Translation\Drivers\GoogleTranslateDriver;
use App\Services\Translation\Drivers\TencentCloudTranslateDriver;
use Illuminate\Contracts\Container\Container;

/**
 * 按翻译供应商解析并缓存对应的驱动实例。
 *
 * 同一供应商的驱动在单次请求内复用，协议与驱动的映射由代码明确维护。
 */
class TranslatorManager
{
    /**
     * @var array<string, TranslatorContract>
     */
    private array $cache = [];

    /**
     * 注入容器以解析驱动，并支持测试替换具体实现。
     */
    public function __construct(private readonly Container $container) {}

    /**
     * 返回翻译供应商对应的驱动实例。
     *
     * 同一请求内复用同一供应商的驱动，fresh 为 true 时创建独立实例。
     */
    public function driverFor(TranslationProvider $provider, bool $fresh = false): TranslatorContract
    {
        if (! $fresh && isset($this->cache[$provider->id])) {
            return $this->cache[$provider->id];
        }

        $driverClass = $this->resolveDriverClass($provider->protocol);

        /** @var TranslatorContract $driver */
        $driver = $this->container->make($driverClass, ['provider' => $provider]);

        if (! $fresh) {
            $this->cache[$provider->id] = $driver;
        }

        return $driver;
    }

    /**
     * 将供应商协议映射到具体驱动类。
     *
     * @return class-string<TranslatorContract>
     */
    private function resolveDriverClass(TranslationProviderType $protocol): string
    {
        return match ($protocol) {
            TranslationProviderType::GoogleTranslate => GoogleTranslateDriver::class,
            TranslationProviderType::DeepL => DeepLDriver::class,
            TranslationProviderType::AzureTranslator => AzureTranslatorDriver::class,
            TranslationProviderType::BaiduTranslate => BaiduTranslateDriver::class,
            TranslationProviderType::TencentCloudTranslate => TencentCloudTranslateDriver::class,
            TranslationProviderType::AmazonTranslate => AmazonTranslateDriver::class,
            TranslationProviderType::DeepSeek => DeepSeekTranslateDriver::class,
        };
    }
}
