<?php

namespace App\Data\Channel\WechatOfficialAccount;

use App\Enums\ReceptionLanguage;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/** 接收微信公众号渠道创建表单。 */
class FormCreateWechatOfficialAccountChannelData extends Data
{
    /** 创建微信公众号渠道表单数据。 */
    public function __construct(
        public string $name,
        public string $reception_plan_id,
        public ReceptionLanguage $default_visitor_locale = ReceptionLanguage::ChineseSimplified,
        public ?string $description = null,
    ) {}

    /** 定义微信公众号渠道创建规则。 */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reception_plan_id' => ['required', 'string', 'ulid'],
            'default_visitor_locale' => ['required', Rule::enum(ReceptionLanguage::class)],
        ];
    }
}
