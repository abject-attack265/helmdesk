<?php

namespace Database\Factories;

use App\Enums\TranslationProviderType;
use App\Models\TranslationProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TranslationProvider>
 */
class TranslationProviderFactory extends Factory
{
    /**
     * 返回默认 DeepSeek 翻译供应商测试数据。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $suffix = Str::lower(Str::random(6));

        return [
            'slug' => 'deepseek-'.$suffix,
            'name' => 'DeepSeek '.Str::upper($suffix),
            'protocol' => TranslationProviderType::DeepSeek,
            'icon' => null,
            'credentials' => ['api_key' => 'test-api-key'],
            'credential_fields' => [
                ['field' => 'api_key', 'label' => 'API Key', 'required' => true, 'secret' => true],
            ],
            'options' => null,
            'is_active' => true,
        ];
    }

    /**
     * 返回停用状态。
     */
    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * 返回缺少凭据状态。
     */
    public function withoutCredentials(): self
    {
        return $this->state(fn (): array => ['credentials' => []]);
    }

    /**
     * 返回 Google 翻译供应商测试数据。
     */
    public function google(): self
    {
        return $this->state(fn (): array => [
            'protocol' => TranslationProviderType::GoogleTranslate,
            'icon' => 'google',
            'credentials' => ['api_key' => 'test-google-api-key'],
            'credential_fields' => [
                ['field' => 'api_key', 'label' => 'API Key', 'required' => true, 'secret' => true],
            ],
        ]);
    }
}
