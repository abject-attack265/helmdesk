<?php

namespace App\Data\AiProvider;

use App\Services\AiProvider\AiProviderCatalog;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 创建AI 供应商表单数据。
 *
 * 来自 resources/js/pages/appSettings/aiProvider/Create.vue 的提交：
 * 管理员从品牌目录选 brand、填展示名称和凭据 configuration，后端据此创建供应商记录（不播种模型）。
 */
class FormCreateAiProviderData extends Data
{
    public function __construct(
        public string $brand,
        public string $name,
        /** @var array<string, mixed> */
        public array $configuration = [],
    ) {}

    /**
     * 校验 brand 取自品牌目录；所有品牌都要求填写展示名称，方便区分多份同品牌配置。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        $brands = array_keys(app(AiProviderCatalog::class)->brands());

        return [
            'brand' => ['required', 'string', Rule::in($brands)],
            'name' => ['required', 'string', 'max:128'],
            'configuration' => ['nullable', 'array'],
            'configuration.*' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
