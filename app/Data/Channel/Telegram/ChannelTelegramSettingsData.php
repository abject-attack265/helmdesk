<?php

namespace App\Data\Channel\Telegram;

use App\Enums\ReceptionLanguage;
use App\Enums\TelegramWebhookMode;
use Spatie\LaravelData\Data;

/** Telegram 渠道设置，供渠道管理页与消息链路使用。 */
class ChannelTelegramSettingsData extends Data
{
    /** 创建 Telegram 渠道设置；机器人身份来自 getMe。 */
    public function __construct(
        public ?string $bot_token = null,
        public string $webhook_secret = '',
        public ?string $bot_username = null,
        public ?int $bot_id = null,
        public ReceptionLanguage $default_visitor_locale = ReceptionLanguage::ChineseSimplified,
        public bool $visitor_message_ai_translation_enabled = false,
        public ?string $translation_context_hint = null,
        public ?string $webhook_registered_at = null,
        public TelegramWebhookMode $webhook_mode = TelegramWebhookMode::Direct,
    ) {}

    /**
     * 使用默认值与指定覆盖项构造 Telegram 渠道设置。
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function defaults(array $overrides = []): self
    {
        return self::from(array_merge([
            'bot_token' => null,
            'webhook_secret' => '',
            'bot_username' => null,
            'bot_id' => null,
            'default_visitor_locale' => ReceptionLanguage::ChineseSimplified->value,
            'visitor_message_ai_translation_enabled' => false,
            'translation_context_hint' => null,
            'webhook_registered_at' => null,
            'webhook_mode' => TelegramWebhookMode::Direct->value,
        ], $overrides));
    }

    /** 复制当前设置并替换 webhook 注册时间。 */
    public function withWebhookRegisteredAt(?string $registeredAt): self
    {
        return new self(
            bot_token: $this->bot_token,
            webhook_secret: $this->webhook_secret,
            bot_username: $this->bot_username,
            bot_id: $this->bot_id,
            default_visitor_locale: $this->default_visitor_locale,
            visitor_message_ai_translation_enabled: $this->visitor_message_ai_translation_enabled,
            translation_context_hint: $this->translation_context_hint,
            webhook_registered_at: $registeredAt,
            webhook_mode: $this->webhook_mode,
        );
    }

    /** 复制当前设置并写入 Telegram 返回的机器人身份。 */
    public function withBotIdentity(int $botId, ?string $botUsername): self
    {
        return new self(
            bot_token: $this->bot_token,
            webhook_secret: $this->webhook_secret,
            bot_username: $botUsername,
            bot_id: $botId,
            default_visitor_locale: $this->default_visitor_locale,
            visitor_message_ai_translation_enabled: $this->visitor_message_ai_translation_enabled,
            translation_context_hint: $this->translation_context_hint,
            webhook_registered_at: $this->webhook_registered_at,
            webhook_mode: $this->webhook_mode,
        );
    }

    /** 复制当前设置并替换 webhook 归属模式。 */
    public function withWebhookMode(TelegramWebhookMode $mode): self
    {
        return new self(
            bot_token: $this->bot_token,
            webhook_secret: $this->webhook_secret,
            bot_username: $this->bot_username,
            bot_id: $this->bot_id,
            default_visitor_locale: $this->default_visitor_locale,
            visitor_message_ai_translation_enabled: $this->visitor_message_ai_translation_enabled,
            translation_context_hint: $this->translation_context_hint,
            webhook_registered_at: $this->webhook_registered_at,
            webhook_mode: $mode,
        );
    }
}
