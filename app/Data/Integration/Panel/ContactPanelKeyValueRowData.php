<?php

namespace App\Data\Integration\Panel;

use App\Enums\ContactPanelValueType;
use Spatie\LaravelData\Data;

/**
 * 联系人面板 key-value 积木块的单行（label + value + 渲染提示）。
 *
 * 由 IntegrationPanel.vue 的 KeyValueBlock 逐行渲染；value_type 决定 value 字符串的呈现方式。
 */
class ContactPanelKeyValueRowData extends Data
{
    public function __construct(
        public string $label,
        public string $value,
        public ContactPanelValueType $value_type,
    ) {}
}
