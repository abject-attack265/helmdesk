<?php

use App\Models\TranslationProvider;
use App\Services\Translation\Drivers\GoogleTranslateDriver;
use App\Services\Translation\Exceptions\TranslationProviderException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->provider = TranslationProvider::factory()->google()->create([
        'slug' => 'google-test',
        'credentials' => ['api_key' => 'fake-key'],
    ]);
});

it('翻译文本并返回统一结果', function () {
    Http::fake([
        'translation.googleapis.com/*' => Http::response([
            'data' => [
                'translations' => [
                    ['translatedText' => '你好', 'detectedSourceLanguage' => 'en'],
                ],
            ],
        ]),
    ]);

    $result = (new GoogleTranslateDriver($this->provider))
        ->translate('Hello', 'en', 'zh-CN');

    expect($result->text)->toBe('你好')
        ->and($result->source_lang)->toBe('en')
        ->and($result->target_lang)->toBe('zh-CN')
        ->and($result->provider_slug)->toBe('google-test')
        ->and($result->model)->toBeNull()
        ->and($result->char_count)->toBe(5);
});

it('自动识别源语言并解码译文中的 HTML 实体', function () {
    Http::fake([
        'translation.googleapis.com/*' => Http::response([
            'data' => [
                'translations' => [
                    ['translatedText' => 'Tom &amp; Jerry', 'detectedSourceLanguage' => 'en'],
                ],
            ],
        ]),
    ]);

    $result = (new GoogleTranslateDriver($this->provider))
        ->translate('Tom and Jerry', 'auto', 'es');

    expect($result->text)->toBe('Tom & Jerry')
        ->and($result->source_lang)->toBe('en');

    Http::assertSent(function ($request): bool {
        $data = $request->data();

        return ! array_key_exists('source', $data)
            && ($data['target'] ?? null) === 'es';
    });
});

it('包装上游错误并保留供应商与状态码', function () {
    Http::fake([
        'translation.googleapis.com/*' => Http::response([
            'error' => ['message' => 'API key not valid'],
        ], 400),
    ]);

    try {
        (new GoogleTranslateDriver($this->provider))
            ->translate('Hello', 'en', 'zh-CN');
        $this->fail('未抛出 TranslationProviderException。');
    } catch (TranslationProviderException $exception) {
        expect($exception->statusCode)->toBe(400)
            ->and($exception->providerSlug)->toBe('google-test')
            ->and($exception->getMessage())->toContain('API key not valid');
    }
});

it('包装连接异常', function () {
    Http::fake(function () {
        throw new ConnectionException('Operation timed out');
    });

    try {
        (new GoogleTranslateDriver($this->provider))
            ->translate('Hello', 'en', 'zh-CN');
        $this->fail('未抛出 TranslationProviderException。');
    } catch (TranslationProviderException $exception) {
        expect($exception->statusCode)->toBeNull()
            ->and($exception->providerSlug)->toBe('google-test')
            ->and($exception->getPrevious())->toBeInstanceOf(ConnectionException::class);
    }
});
