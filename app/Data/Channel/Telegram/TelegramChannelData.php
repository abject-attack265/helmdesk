<?php

namespace App\Data\Channel\Telegram;

use App\Data\AiRuntime\ModelSelectionStatusData;
use App\Enums\ReceptionLanguage;
use App\Enums\TelegramWebhookMode;
use App\Models\Channel;
use App\Services\Telegram\TelegramWebhookUrl;
use Spatie\LaravelData\Data;

/** Telegram 渠道列表和详情页使用的连接状态数据。 */
class TelegramChannelData extends Data
{
    /**
     * 封装渠道信息、连接状态和按权限下发的转发密钥。
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public string $code,
        public ?string $bot_username,
        public bool $has_bot_token,
        public string $webhook_url,
        public bool $webhook_active,
        public ?string $webhook_registered_at,
        public TelegramWebhookMode $webhook_mode,
        /** 外部系统转发消息时使用的鉴权密钥。 */
        public ?string $webhook_secret,
        public ?string $reception_plan_id,
        public ?string $reception_plan_name,
        public ?ModelSelectionStatusData $reception_plan_status_detail,
        public ReceptionLanguage $default_visitor_locale,
        public bool $visitor_message_ai_translation_enabled,
        public ?string $translation_context_hint,
        public ?string $updated_at,
        public ?string $deleted_at,
    ) {}

    /**
     * 从渠道模型组装展示数据，注册成功且未暂停的 webhook 标记为活跃。
     */
    public static function fromModel(Channel $channel, ?ModelSelectionStatusData $planStatus = null, bool $withSecrets = false): self
    {
        $settings = $channel->telegramSettings();

        $plan = $channel->relationLoaded('receptionPlan')
            ? $channel->receptionPlan
            : $channel->receptionPlan()->first();

        return new self(
            id: (string) $channel->id,
            name: $channel->name,
            description: $channel->description,
            code: $channel->code,
            bot_username: $settings->bot_username,
            has_bot_token: filled($settings->bot_token),
            webhook_url: TelegramWebhookUrl::for($channel->code),
            webhook_active: ! $channel->trashed() && filled($settings->webhook_registered_at),
            webhook_registered_at: $settings->webhook_registered_at,
            webhook_mode: $settings->webhook_mode,
            webhook_secret: $withSecrets && $settings->webhook_mode === TelegramWebhookMode::Gateway
                ? $settings->webhook_secret
                : null,
            reception_plan_id: filled($channel->reception_plan_id) ? (string) $channel->reception_plan_id : null,
            reception_plan_name: filled($plan?->name) ? (string) $plan->name : null,
            reception_plan_status_detail: $planStatus,
            default_visitor_locale: $settings->default_visitor_locale,
            visitor_message_ai_translation_enabled: $settings->visitor_message_ai_translation_enabled,
            translation_context_hint: $settings->translation_context_hint,
            updated_at: $channel->updated_at?->toIso8601String(),
            deleted_at: $channel->deleted_at?->toIso8601String(),
        );
    }
}
