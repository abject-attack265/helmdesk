<?php

namespace App\Data\AiModel;

use Spatie\LaravelData\Data;

/**
 * 品牌预设模型目录项，供「添加模型」时按所选供应商品牌一键带出 model_id（不带名称/用途）。
 * description 为该模型的简短说明，在「预设模型」选择弹窗里展示。
 */
class CatalogModelOptionData extends Data
{
    public function __construct(
        public string $model_id,
        public string $name,
        public string $type,
        public string $description,
    ) {}

    /**
     * 从 AiProviderCatalog::defaultModelsForBrand() 的单条 spec 构造。
     *
     * @param  array<string, mixed>  $spec
     */
    public static function fromCatalogSpec(array $spec): self
    {
        return new self(
            model_id: (string) ($spec['model_id'] ?? ''),
            name: (string) ($spec['name'] ?? ''),
            type: (string) ($spec['type'] ?? ''),
            description: (string) ($spec['description'] ?? ''),
        );
    }
}
