<?php

namespace App\Providers;

use App\Services\Translation\TranslatorManager;
use Illuminate\Support\ServiceProvider;

/**
 * 注册翻译领域的服务。
 *
 * TranslatorManager 用 scoped 而非 singleton：Octane 下保持每请求一份实例，
 * 防止 driver 缓存跨请求残留 provider 凭据。
 */
class TranslationServiceProvider extends ServiceProvider
{
    /**
     * 把 TranslatorManager 注册为请求级单例，确保 driver 缓存随请求生命周期被回收。
     */
    public function register(): void
    {
        $this->app->scoped(TranslatorManager::class, fn ($app): TranslatorManager => new TranslatorManager($app));
    }
}
