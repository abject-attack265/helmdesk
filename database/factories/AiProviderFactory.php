<?php

namespace Database\Factories;

use App\Enums\AiProviderProtocol;
use App\Models\AiProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * 全局 AI 供应商工厂（系统级，跨应用共享）。
 *
 * AiProvider 模型未启用 HasFactory，故测试中以 AiProviderFactory::new() 直接构造；
 * 默认填好品牌、协议、完整凭据与凭据字段定义，让其 hasCompleteCredentials() 为真。
 *
 * @extends Factory<AiProvider>
 */
class AiProviderFactory extends Factory
{
    /**
     * @var class-string<AiProvider>
     */
    protected $model = AiProvider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $suffix = Str::lower(Str::random(6));

        return [
            'brand' => 'openai',
            'slug' => 'openai-'.$suffix,
            'name' => 'OpenAI '.Str::upper($suffix),
            'protocol' => AiProviderProtocol::OpenAI,
            'icon' => null,
            'credentials' => ['key' => 'test-key'],
            'credential_fields' => [
                ['field' => 'key', 'label' => 'API Key', 'type' => 'password', 'required' => true, 'secret' => true],
            ],
        ];
    }

    /**
     * 标记为缺失必填凭据（凭据不完整，不进入运行时取用池）。
     */
    public function withoutCredentials(): self
    {
        return $this->state(fn (): array => ['credentials' => []]);
    }
}
