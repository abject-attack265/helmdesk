<?php

namespace App\Services\Translation;

use App\Enums\TranslationProviderSelectionStrategy;
use App\Models\TranslationProvider;
use App\Services\LocalePreference;
use App\Services\Translation\Exceptions\TranslationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 全局翻译供应商轮询池。
 *
 * 供应商按调用策略排序并在失败时依次轮询，自动翻译结果按内容和语言缓存。
 */
class TranslationProviderPool
{
    /**
     * 注入驱动管理器，按供应商协议解析具体驱动。
     */
    public function __construct(
        private readonly TranslatorManager $manager,
    ) {}

    /**
     * 返回启用且凭据完整的供应商，并按策略排列候选顺序。
     *
     * @return Collection<int, TranslationProvider>
     */
    public function usableProviders(
        TranslationProviderSelectionStrategy $strategy = TranslationProviderSelectionStrategy::MachineFirst,
        ?string $excludeProviderSlug = null,
    ): Collection {
        $providers = TranslationProvider::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (TranslationProvider $provider): bool => $provider->hasCompleteCredentials())
            ->values();

        if ($excludeProviderSlug !== null && $providers->count() > 1) {
            $providers = $providers
                ->reject(fn (TranslationProvider $provider): bool => $provider->slug === $excludeProviderSlug)
                ->values();
        }

        $providers = $providers->shuffle();

        return match ($strategy) {
            TranslationProviderSelectionStrategy::MachineFirst => $providers
                ->sortBy(fn (TranslationProvider $provider): bool => $provider->protocol->isLlm())
                ->values(),
            TranslationProviderSelectionStrategy::AiFirst => $providers
                ->sortByDesc(fn (TranslationProvider $provider): bool => $provider->protocol->isLlm())
                ->values(),
            TranslationProviderSelectionStrategy::Random => $providers->values(),
        };
    }

    /**
     * 判断当前是否存在可用于运行时翻译的供应商。
     */
    public function hasUsable(): bool
    {
        return TranslationProvider::query()
            ->where('is_active', true)
            ->get()
            ->contains(fn (TranslationProvider $provider): bool => $provider->hasCompleteCredentials());
    }

    /**
     * 从自动翻译缓存或有序供应商池中取得译文。
     *
     * 自动翻译缓存按候选供应商、选择策略、语言、内容和渠道语境隔离。
     *
     * @param  array<string, mixed>  $options  驱动可选参数，如大模型的 context_hint
     * @param  bool  $force  强制重翻时跳过缓存读取和写入
     */
    public function translate(
        string $content,
        string $sourceLang,
        string $targetLang,
        array $options = [],
        bool $force = false,
        TranslationProviderSelectionStrategy $strategy = TranslationProviderSelectionStrategy::MachineFirst,
        ?string $excludeProviderSlug = null,
    ): TranslationResult {
        $providers = $this->usableProviders($strategy, $excludeProviderSlug);

        if ($providers->isEmpty()) {
            throw new TranslationException(__('translation.driver_errors.no_default_provider'));
        }

        if (
            $excludeProviderSlug !== null
            && $providers->count() === 1
            && $providers->first()->slug === $excludeProviderSlug
        ) {
            Log::info('没有可替代的翻译供应商，继续使用当前供应商', [
                'provider_slug' => $excludeProviderSlug,
                'selection_strategy' => $strategy->value,
                'call_context' => $options['call_context'] ?? null,
            ]);
        }

        $cacheKey = 'message_translation:'.sha1((string) json_encode([
            'pool' => $this->fingerprint($providers),
            'source_lang' => $sourceLang,
            'target_lang' => $targetLang,
            'content' => $content,
            'context_hint' => $options['context_hint'] ?? null,
            'selection_strategy' => $strategy->value,
        ], JSON_THROW_ON_ERROR));

        if (! $force) {
            $cached = Cache::get($cacheKey);
            if ($cached !== null) {
                return TranslationResult::from($cached);
            }
        }

        $result = $this->translateRotating($providers, $content, $sourceLang, $targetLang, $strategy, $options);

        $payload = [
            'text' => $result->text,
            'source_lang' => $result->source_lang,
            'target_lang' => $result->target_lang,
            'provider_slug' => $result->provider_slug,
            'model' => $result->model,
            'latency_ms' => $result->latency_ms,
            'char_count' => $result->char_count,
        ];

        if (! $force) {
            Cache::put($cacheKey, $payload, now()->addDays(30));
        }

        return $result;
    }

    /**
     * 依次尝试池中供应商，第一个成功即返回；全部失败抛出最后一次异常。
     *
     * @param  Collection<int, TranslationProvider>  $providers
     * @param  array<string, mixed>  $options
     */
    private function translateRotating(
        Collection $providers,
        string $content,
        string $sourceLang,
        string $targetLang,
        TranslationProviderSelectionStrategy $strategy,
        array $options = [],
    ): TranslationResult {
        $lastException = null;
        $attempts = 0;
        $providerCount = $providers->count();

        foreach ($providers as $provider) {
            $attempts++;

            try {
                $result = $this->manager->driverFor($provider)->translate($content, $sourceLang, $targetLang, $options);

                if (! LocalePreference::matches($result->target_lang, $targetLang)) {
                    $lastException = new TranslationException(__('translation.driver_errors.target_language_mismatch', [
                        'provider' => $provider->name,
                        'expected' => $targetLang,
                        'actual' => $result->target_lang,
                    ]));

                    Log::warning('翻译供应商返回的目标语言不匹配', [
                        'provider_id' => $provider->id,
                        'provider_slug' => $provider->slug,
                        'selection_strategy' => $strategy->value,
                        'requested_target_lang' => $targetLang,
                        'returned_target_lang' => $result->target_lang,
                        'attempt' => $attempts,
                        'provider_count' => $providerCount,
                        'will_retry' => $attempts < $providerCount,
                        'call_context' => $options['call_context'] ?? null,
                    ]);

                    continue;
                }

                Log::info('翻译完成', [
                    'provider_slug' => $result->provider_slug,
                    'selection_strategy' => $strategy->value,
                    'requested_target_lang' => $targetLang,
                    'target_lang' => $result->target_lang,
                    'char_count' => $result->char_count,
                    'latency_ms' => $result->latency_ms,
                    'attempts' => $attempts,
                    'call_context' => $options['call_context'] ?? null,
                ]);

                return $result;
            } catch (TranslationException $exception) {
                $lastException = $exception;
                Log::warning('翻译供应商调用失败', [
                    'provider_id' => $provider->id,
                    'provider_slug' => $provider->slug,
                    'selection_strategy' => $strategy->value,
                    'attempt' => $attempts,
                    'provider_count' => $providerCount,
                    'will_retry' => $attempts < $providerCount,
                    'call_context' => $options['call_context'] ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        throw $lastException;
    }

    /**
     * 生成不受候选顺序影响、随供应商配置变化失效的池指纹。
     *
     * @param  Collection<int, TranslationProvider>  $providers
     * @return list<string>
     */
    private function fingerprint(Collection $providers): array
    {
        return $providers
            ->map(fn (TranslationProvider $provider): string => $provider->id.':'.($provider->updated_at?->timestamp ?? 0))
            ->sort()
            ->values()
            ->all();
    }
}
