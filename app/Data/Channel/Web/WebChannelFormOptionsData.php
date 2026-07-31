<?php

namespace App\Data\Channel\Web;

use App\Data\EnumOptionData;
use App\Data\Reception\Plan\ReceptionPlanOptionData;
use Spatie\LaravelData\Data;

/**
 * 网站渠道表单选项。
 * 由渠道创建/编辑页 props 携带，前端用于渲染查询参数、主题预设、入口选项以及可绑定的接待方案下拉。
 */
class WebChannelFormOptionsData extends Data
{
    public function __construct(
        /** @var ReceptionPlanOptionData[] */
        public array $reception_plan_options,
        /** @var EnumOptionData[] */
        public array $visitor_identity_mode_options,
        /** @var EnumOptionData[] */
        public array $query_param_options,
        /** @var list<string> */
        public array $theme_color_options,
        /** @var EnumOptionData[] */
        public array $widget_entry_mode_options,
        /** @var EnumOptionData[] */
        public array $widget_entry_position_options,
        /** @var EnumOptionData[] */
        public array $widget_entry_style_options,
        /** @var EnumOptionData[] */
        public array $widget_icon_size_options,
        /** @var EnumOptionData[] */
        public array $query_param_target_options,
        /** @var EnumOptionData[] */
        public array $query_param_trust_options,
        /** @var EnumOptionData[] */
        public array $query_param_write_mode_options,
        /** @var WritableAttributeDefinitionOptionData[] */
        public array $writable_attribute_definition_options,
        /** @var EnumOptionData[] */
        public array $reception_language_options,
    ) {}
}
