<?php

namespace App\Data\Channel\WechatOfficialAccount;

use App\Enums\ReceptionLanguage;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/** 接收微信公众号渠道配置表单。 */
class FormUpdateWechatOfficialAccountChannelBasicData extends Data
{
    /** 创建微信公众号渠道配置表单数据。 */
    public function __construct(
        public string $name,
        public string $app_id,
        public string $app_secret,
        public string $token,
        public string $message_mode,
        public string $reception_plan_id,
        public ReceptionLanguage $default_visitor_locale,
        public ?string $aes_key = null,
        public ?string $description = null,
        public bool $visitor_message_ai_translation_enabled = false,
        public ?string $translation_context_hint = null,
    ) {}

    /** 定义微信公众号渠道配置更新规则。 */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'app_id' => ['required', 'string', 'max:64'],
            'app_secret' => ['required', 'string', 'max:128'],
            'token' => ['required', 'string', 'max:128'],
            'message_mode' => ['required', Rule::in(['plain', 'aes'])],
            'aes_key' => ['required_if:message_mode,aes', 'nullable', 'string', 'size:43', 'regex:/^[A-Za-z0-9]{43}$/'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reception_plan_id' => ['required', 'string', 'ulid'],
            'default_visitor_locale' => ['required', Rule::enum(ReceptionLanguage::class)],
            'visitor_message_ai_translation_enabled' => ['boolean'],
            'translation_context_hint' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
