<?php

namespace App\Data\Channel\Telegram;

use App\Enums\ReceptionLanguage;
use App\Enums\TelegramWebhookMode;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/** Telegram 渠道详情页提交的基本信息和连接设置。 */
class FormUpdateTelegramChannelBasicData extends Data
{
    /** 创建 Telegram 渠道基本信息表单数据。 */
    public function __construct(
        public string $name,
        public string $reception_plan_id,
        public ReceptionLanguage $default_visitor_locale,
        public TelegramWebhookMode $webhook_mode,
        public ?string $bot_token = null,
        public ?string $description = null,
        public bool $visitor_message_ai_translation_enabled = false,
        public ?string $translation_context_hint = null,
    ) {}

    /**
     * 返回 Telegram 渠道基本信息的校验规则。
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'reception_plan_id' => ['required', 'string', 'ulid'],
            'default_visitor_locale' => ['required', Rule::enum(ReceptionLanguage::class)],
            'webhook_mode' => ['required', Rule::enum(TelegramWebhookMode::class)],
            'bot_token' => ['nullable', 'string', 'max:200', 'regex:/^\d+:[A-Za-z0-9_-]{20,}$/'],
            'visitor_message_ai_translation_enabled' => ['boolean'],
            'translation_context_hint' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
