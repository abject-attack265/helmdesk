<?php

use App\Enums\TranslationProviderType;
use App\Models\TranslationProvider;
use App\Services\Translation\Drivers\AmazonTranslateDriver;
use App\Services\Translation\Drivers\AzureTranslatorDriver;
use App\Services\Translation\Drivers\BaiduTranslateDriver;
use App\Services\Translation\Drivers\DeepLDriver;
use App\Services\Translation\Drivers\TencentCloudTranslateDriver;
use App\Services\Translation\Exceptions\TranslationProviderException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * 创建指定协议的翻译供应商。
 *
 * @param  array<string, string>  $credentials
 */
function makeTranslationProvider(
    TranslationProviderType $protocol,
    string $slug,
    array $credentials,
): TranslationProvider {
    return TranslationProvider::factory()->create([
        'slug' => $slug,
        'name' => $protocol->label(),
        'protocol' => $protocol,
        'credentials' => $credentials,
    ]);
}

it('使用 DeepL 翻译文本', function () {
    $provider = makeTranslationProvider(TranslationProviderType::DeepL, 'deepl-test', [
        'auth_key' => 'deepl-key',
    ]);
    Http::fake([
        'api.deepl.com/*' => Http::response([
            'translations' => [
                ['detected_source_language' => 'EN', 'text' => '你好'],
            ],
        ]),
    ]);

    $result = (new DeepLDriver($provider))->translate('Hello', 'auto', 'zh-CN');

    expect($result->text)->toBe('你好')
        ->and($result->source_lang)->toBe('EN')
        ->and($result->provider_slug)->toBe('deepl-test');
    Http::assertSent(function (Request $request): bool {
        $data = $request->data();

        return $request->hasHeader('Authorization', 'DeepL-Auth-Key deepl-key')
            && ($data['target_lang'] ?? null) === 'ZH'
            && ! array_key_exists('source_lang', $data);
    });
});

it('使用 Azure Translator 翻译文本', function () {
    $provider = makeTranslationProvider(TranslationProviderType::AzureTranslator, 'azure-test', [
        'api_key' => 'azure-key',
        'region' => 'eastus',
    ]);
    Http::fake([
        'api.cognitive.microsofttranslator.com/*' => Http::response([
            [
                'detectedLanguage' => ['language' => 'en'],
                'translations' => [['text' => '你好', 'to' => 'zh-Hans']],
            ],
        ]),
    ]);

    $result = (new AzureTranslatorDriver($provider))->translate('Hello', 'auto', 'zh-CN');

    expect($result->text)->toBe('你好')
        ->and($result->source_lang)->toBe('en');
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'to=zh-Hans')
        && ! str_contains($request->url(), 'from=')
        && $request->hasHeader('Ocp-Apim-Subscription-Key', 'azure-key')
        && $request->hasHeader('Ocp-Apim-Subscription-Region', 'eastus'));
});

it('使用百度翻译文本', function () {
    $provider = makeTranslationProvider(TranslationProviderType::BaiduTranslate, 'baidu-test', [
        'app_id' => 'baidu-app',
        'app_secret' => 'baidu-secret',
    ]);
    Http::fake([
        'fanyi-api.baidu.com/*' => Http::response([
            'from' => 'en',
            'to' => 'zh',
            'trans_result' => [['src' => 'Hello', 'dst' => '你好']],
        ]),
    ]);

    $result = (new BaiduTranslateDriver($provider))->translate('Hello', 'en', 'zh-CN');

    expect($result->text)->toBe('你好')
        ->and($result->source_lang)->toBe('en');
    Http::assertSent(function (Request $request): bool {
        $data = $request->data();

        return ($data['to'] ?? null) === 'zh'
            && ($data['sign'] ?? null) === md5('baidu-app'.'Hello'.$data['salt'].'baidu-secret');
    });
});

it('使用腾讯云机器翻译文本', function () {
    $provider = makeTranslationProvider(TranslationProviderType::TencentCloudTranslate, 'tencent-test', [
        'secret_id' => 'tencent-id',
        'secret_key' => 'tencent-key',
        'region' => 'ap-guangzhou',
    ]);
    Http::fake([
        'tmt.tencentcloudapi.com*' => Http::response([
            'Response' => [
                'Source' => 'en',
                'TargetText' => '你好',
            ],
        ]),
    ]);

    $result = (new TencentCloudTranslateDriver($provider))->translate('Hello', 'en', 'zh-CN');

    expect($result->text)->toBe('你好');
    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);

        return $request->hasHeader('X-TC-Action', 'TextTranslate')
            && str_starts_with((string) $request->header('Authorization')[0], 'TC3-HMAC-SHA256 Credential=tencent-id/')
            && ($body['Target'] ?? null) === 'zh';
    });
});

it('使用 Amazon Translate 翻译文本', function () {
    $provider = makeTranslationProvider(TranslationProviderType::AmazonTranslate, 'amazon-test', [
        'access_key_id' => 'aws-access',
        'secret_access_key' => 'aws-secret',
        'region' => 'us-east-1',
    ]);
    Http::fake([
        'translate.us-east-1.amazonaws.com*' => Http::response([
            'TranslatedText' => '你好',
            'SourceLanguageCode' => 'en',
        ]),
    ]);

    $result = (new AmazonTranslateDriver($provider))->translate('Hello', 'en', 'zh-CN');

    expect($result->text)->toBe('你好');
    Http::assertSent(function (Request $request): bool {
        $body = json_decode($request->body(), true);

        return $request->hasHeader('X-Amz-Target', 'AWSShineFrontendService_20170701.TranslateText')
            && str_starts_with((string) $request->header('Authorization')[0], 'AWS4-HMAC-SHA256 Credential=aws-access/')
            && ($body['TargetLanguageCode'] ?? null) === 'zh';
    });
});

it('使用临时会话凭据签名 Amazon Translate 请求', function () {
    $provider = makeTranslationProvider(TranslationProviderType::AmazonTranslate, 'amazon-session-test', [
        'access_key_id' => 'aws-access',
        'secret_access_key' => 'aws-secret',
        'session_token' => 'aws-session-token',
        'region' => 'us-east-1',
    ]);
    Http::fake([
        'translate.us-east-1.amazonaws.com*' => Http::response([
            'TranslatedText' => '你好',
            'SourceLanguageCode' => 'en',
        ]),
    ]);

    (new AmazonTranslateDriver($provider))->translate('Hello', 'en', 'zh-CN');

    Http::assertSent(function (Request $request): bool {
        $authorization = (string) $request->header('Authorization')[0];

        return $request->hasHeader('X-Amz-Security-Token', 'aws-session-token')
            && str_contains($authorization, 'x-amz-security-token');
    });
});

it('机器翻译驱动包装上游错误', function () {
    $provider = makeTranslationProvider(TranslationProviderType::DeepL, 'deepl-error-test', [
        'auth_key' => 'deepl-key',
    ]);
    Http::fake([
        'api.deepl.com/*' => Http::response(['message' => 'Authorization failed'], 403),
    ]);

    expect(fn () => (new DeepLDriver($provider))->translate('Hello', 'en', 'zh-CN'))
        ->toThrow(TranslationProviderException::class, 'Authorization failed');
});
