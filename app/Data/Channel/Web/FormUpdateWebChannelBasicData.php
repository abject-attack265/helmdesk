<?php

namespace App\Data\Channel\Web;

use App\Enums\ReceptionLanguage;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/** 网站渠道基本信息更新表单。 */
class FormUpdateWebChannelBasicData extends Data
{
    /** 创建网站渠道基本信息表单数据。 */
    public function __construct(
        public string $name,
        public string $reception_plan_id,
        public ?string $description = null,
        public ?ReceptionLanguage $default_visitor_locale = null,
        public bool $visitor_message_ai_translation_enabled = false,
        public ?string $translation_context_hint = null,
    ) {}

    /**
     * 返回网站渠道基本信息校验规则。
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reception_plan_id' => ['required', 'string', 'ulid'],
            'default_visitor_locale' => ['nullable', Rule::enum(ReceptionLanguage::class)],
            'visitor_message_ai_translation_enabled' => ['boolean'],
            'translation_context_hint' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
