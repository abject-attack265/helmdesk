<?php

namespace App\Data\Channel\Web;

use App\Enums\ReceptionLanguage;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 创建网站渠道表单数据。
 * 来自 resources/js/pages/channel/web/Create.vue 的新增表单提交，后端用它做校验并写入网站渠道相关记录。
 */
class FormCreateWebChannelData extends Data
{
    /**
     * 创建网站渠道表单字段。
     *
     * reception_plan_id 必须指向应用内存在可用最新版本的接待方案；渠道自动跟随该方案最新版。
     * default_visitor_locale 是渠道访客界面的默认展示语言。
     */
    public function __construct(
        public string $name,
        public string $reception_plan_id,
        public ?string $description = null,
        public ReceptionLanguage $default_visitor_locale = ReceptionLanguage::ChineseSimplified,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reception_plan_id' => ['required', 'string', 'ulid'],
            'default_visitor_locale' => ['required', 'string', Rule::in(array_column(ReceptionLanguage::cases(), 'value'))],
        ];
    }
}
