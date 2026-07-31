<?php

namespace App\Data\CustomAttribute;

use Spatie\LaravelData\Data;

/**
 * 更新联系人属性Values表单数据。
 * 来自 resources/js/pages/appSettings/datas/Attribute.vue 的编辑表单提交，后端用它校验并保存自定义属性配置。
 */
class FormUpdateContactAttributeValuesData extends Data
{
    public function __construct(
        /** @var array<string, mixed> */
        public array $attributes,
    ) {}

    public static function rules(): array
    {
        return [
            'attributes' => ['required', 'array'],
        ];
    }
}
