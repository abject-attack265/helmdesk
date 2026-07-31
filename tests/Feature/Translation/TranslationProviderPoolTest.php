<?php

use App\Enums\TranslationProviderSelectionStrategy;
use App\Models\TranslationProvider;
use App\Services\Translation\Drivers\DeepSeekTranslateDriver;
use App\Services\Translation\Drivers\GoogleTranslateDriver;
use App\Services\Translation\Exceptions\TranslationException;
use App\Services\Translation\TranslationProviderPool;
use App\Services\Translation\TranslationResult;
use App\Services\Translation\TranslatorContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

it('只使用启用且凭据完整的供应商', function () {
    $usable = TranslationProvider::factory()->create();
    $inactive = TranslationProvider::factory()->inactive()->create();
    $missingCredentials = TranslationProvider::factory()->withoutCredentials()->create();

    $pool = app(TranslationProviderPool::class);
    $ids = $pool->usableProviders()->pluck('id')->all();

    expect($ids)->toContain($usable->id)
        ->not->toContain($inactive->id)
        ->not->toContain($missingCredentials->id)
        ->and($pool->hasUsable())->toBeTrue();
});

it('按首翻策略排列 AI 与机器翻译供应商', function () {
    $machine = TranslationProvider::factory()->google()->create(['slug' => 'machine']);
    $ai = TranslationProvider::factory()->create(['slug' => 'ai']);
    $pool = app(TranslationProviderPool::class);

    expect($pool->usableProviders()->pluck('id')->all())->toBe([$machine->id, $ai->id])
        ->and($pool->usableProviders(TranslationProviderSelectionStrategy::AiFirst)->pluck('id')->all())
        ->toBe([$ai->id, $machine->id]);
});

it('只有一种翻译引擎时所有首翻策略都使用现有供应商', function () {
    $pool = app(TranslationProviderPool::class);
    $ai = TranslationProvider::factory()->create();

    expect($pool->usableProviders()->pluck('id')->all())->toBe([$ai->id])
        ->and($pool->usableProviders(TranslationProviderSelectionStrategy::AiFirst)->pluck('id')->all())
        ->toBe([$ai->id]);

    $ai->delete();
    $machine = TranslationProvider::factory()->google()->create();

    expect($pool->usableProviders()->pluck('id')->all())->toBe([$machine->id])
        ->and($pool->usableProviders(TranslationProviderSelectionStrategy::AiFirst)->pluck('id')->all())
        ->toBe([$machine->id]);
});

it('手动重翻排除当前供应商且单一供应商时允许复用', function () {
    $current = TranslationProvider::factory()->create(['slug' => 'current']);
    $alternative = TranslationProvider::factory()->google()->create(['slug' => 'alternative']);
    $pool = app(TranslationProviderPool::class);

    expect($pool->usableProviders(
        TranslationProviderSelectionStrategy::Random,
        'current',
    )->pluck('id')->all())->toBe([$alternative->id]);

    $alternative->update(['is_active' => false]);

    expect($pool->usableProviders(
        TranslationProviderSelectionStrategy::Random,
        'current',
    )->pluck('id')->all())->toBe([$current->id]);
});

it('没有可用供应商时翻译抛出异常', function () {
    TranslationProvider::factory()->inactive()->create();

    expect(app(TranslationProviderPool::class)->hasUsable())->toBeFalse();

    app(TranslationProviderPool::class)->translate('Hello', 'auto', 'zh-CN');
})->throws(TranslationException::class);

it('DeepSeek 凭据失败时轮询下一条凭据', function () {
    $attempts = 0;

    app()->bind(DeepSeekTranslateDriver::class, function ($app, array $params) use (&$attempts): TranslatorContract {
        return new class($params['provider'], $attempts) implements TranslatorContract
        {
            public function __construct(
                private readonly TranslationProvider $provider,
                private int &$attempts,
            ) {}

            public function translate(string $text, string $sourceLang, string $targetLang, array $options = []): TranslationResult
            {
                $this->attempts++;
                if ($this->attempts === 1) {
                    throw new TranslationException('第一条凭据不可用');
                }

                return new TranslationResult(
                    text: '你好',
                    source_lang: 'en',
                    target_lang: $targetLang,
                    provider_slug: $this->provider->slug,
                    model: 'deepseek-v4-flash',
                    latency_ms: 1,
                    char_count: mb_strlen($text),
                );
            }
        };
    });

    TranslationProvider::factory()->count(2)->create();

    $result = app(TranslationProviderPool::class)->translate('Hello', 'auto', 'zh-CN');

    expect($result->text)->toBe('你好')
        ->and($attempts)->toBe(2);
});

it('强制重翻会跳过缓存重新调用 DeepSeek', function () {
    $attempts = 0;

    app()->bind(DeepSeekTranslateDriver::class, function ($app, array $params) use (&$attempts): TranslatorContract {
        return new class($params['provider'], $attempts) implements TranslatorContract
        {
            public function __construct(
                private readonly TranslationProvider $provider,
                private int &$attempts,
            ) {}

            public function translate(string $text, string $sourceLang, string $targetLang, array $options = []): TranslationResult
            {
                $this->attempts++;

                return new TranslationResult(
                    text: '你好',
                    source_lang: 'en',
                    target_lang: $targetLang,
                    provider_slug: $this->provider->slug,
                    model: 'deepseek-v4-flash',
                    latency_ms: 1,
                    char_count: mb_strlen($text),
                );
            }
        };
    });

    TranslationProvider::factory()->create();
    $pool = app(TranslationProviderPool::class);

    $pool->translate('Hello', 'auto', 'zh-CN');
    $pool->translate('Hello', 'auto', 'zh-CN');
    $pool->translate('Hello', 'auto', 'zh-CN', [], true);

    expect($attempts)->toBe(2);
});

it('强制重翻不覆盖自动翻译缓存', function () {
    $attempts = 0;

    app()->bind(GoogleTranslateDriver::class, function ($app, array $params) use (&$attempts): TranslatorContract {
        return new class($params['provider'], $attempts) implements TranslatorContract
        {
            public function __construct(
                private readonly TranslationProvider $provider,
                private int &$attempts,
            ) {}

            public function translate(string $text, string $sourceLang, string $targetLang, array $options = []): TranslationResult
            {
                $this->attempts++;

                return new TranslationResult(
                    text: "译文-{$this->attempts}",
                    source_lang: 'en',
                    target_lang: $targetLang,
                    provider_slug: $this->provider->slug,
                    model: null,
                    latency_ms: 1,
                    char_count: mb_strlen($text),
                );
            }
        };
    });

    TranslationProvider::factory()->google()->create();
    $pool = app(TranslationProviderPool::class);

    $initial = $pool->translate('Hello', 'auto', 'zh-CN');
    $forced = $pool->translate('Hello', 'auto', 'zh-CN', force: true);
    $cachedAfterForce = $pool->translate('Hello', 'auto', 'zh-CN');

    expect($initial->text)->toBe('译文-1')
        ->and($forced->text)->toBe('译文-2')
        ->and($cachedAfterForce->text)->toBe('译文-1')
        ->and($attempts)->toBe(2);
});

it('目标语言不匹配时轮询下一个供应商', function () {
    $attempts = 0;

    app()->bind(GoogleTranslateDriver::class, function ($app, array $params) use (&$attempts): TranslatorContract {
        return new class($params['provider'], $attempts) implements TranslatorContract
        {
            public function __construct(
                private readonly TranslationProvider $provider,
                private int &$attempts,
            ) {}

            public function translate(string $text, string $sourceLang, string $targetLang, array $options = []): TranslationResult
            {
                $this->attempts++;

                return new TranslationResult(
                    text: $this->attempts === 1 ? 'गलत' : '你好',
                    source_lang: 'en',
                    target_lang: $this->attempts === 1 ? 'hi' : $targetLang,
                    provider_slug: $this->provider->slug,
                    model: null,
                    latency_ms: 1,
                    char_count: mb_strlen($text),
                );
            }
        };
    });

    TranslationProvider::factory()->google()->count(2)->create();

    $result = app(TranslationProviderPool::class)->translate('Hello', 'auto', 'zh-CN');

    expect($result->text)->toBe('你好')
        ->and($attempts)->toBe(2);
});
