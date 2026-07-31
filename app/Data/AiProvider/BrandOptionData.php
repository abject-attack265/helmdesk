<?php

namespace App\Data\AiProvider;

use Spatie\LaravelData\Data;

/**
 * 品牌目录项数据。
 * 传给 resources/js/pages/appSettings/aiProvider/Create.vue 的品牌选择，
 * 渲染「新增供应商」时可选的品牌列表（含凭据字段，前端按所选品牌动态渲染凭据表单）。
 */
class BrandOptionData extends Data
{
    public function __construct(
        public string $brand,
        public string $label,
        public bool $is_custom,
        /** @var array<int, array<string, mixed>> */
        public array $credential_fields,
    ) {}
}
