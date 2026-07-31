<?php

namespace App\Data\AiModel;

use App\Models\AiProvider;
use Spatie\LaravelData\Data;

/**
 * AI 模型管理页「选择供应商」下拉的供应商选项。
 */
class AiProviderOptionData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $brand,
    ) {}

    public static function fromModel(AiProvider $provider): self
    {
        return new self(
            id: $provider->id,
            name: $provider->name,
            brand: $provider->brand,
        );
    }
}
