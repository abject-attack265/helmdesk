<?php

namespace App\Data\Channel\WechatOfficialAccount;

use App\Data\AiRuntime\ModelSelectionStatusData;
use App\Enums\ReceptionLanguage;
use App\Models\Channel;
use Spatie\LaravelData\Data;

/** 微信公众号渠道展示数据。 */
class WechatOfficialAccountData extends Data
{
    /** 创建微信公众号渠道展示数据。 */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public string $code,
        public string $app_id,
        public ?string $app_secret,
        public ?string $token,
        public ?string $aes_key,
        public string $webhook_url,
        public bool $webhook_active,
        public ?string $message_mode,
        public ?string $reception_plan_id,
        public ?string $reception_plan_name,
        public ?ModelSelectionStatusData $reception_plan_status_detail,
        public ReceptionLanguage $default_visitor_locale,
        public bool $visitor_message_ai_translation_enabled,
        public ?string $translation_context_hint,
        public ?string $updated_at,
        public ?string $deleted_at,
    ) {}

    /** 从渠道模型构建展示数据，仅在调用方确认权限后下发完整密钥。 */
    public static function fromModel(Channel $channel, ?ModelSelectionStatusData $planStatus = null, bool $withSecrets = false): self
    {
        /** @var ChannelWechatOfficialAccountSettingsData $settings */
        $settings = $channel->settings;
        $plan = $channel->receptionPlan;

        return new self(
            id: (string) $channel->id,
            name: $channel->name,
            description: $channel->description,
            code: $channel->code,
            app_id: $settings->app_id,
            app_secret: $withSecrets ? $settings->app_secret : null,
            token: $withSecrets ? $settings->token : null,
            aes_key: $withSecrets ? $settings->aes_key : null,
            webhook_url: route('public.wechat.webhook', ['code' => $channel->code]),
            webhook_active: ! $channel->trashed() && $settings->isConfigured(),
            message_mode: $settings->isConfigured() ? ($settings->usesEncryption() ? 'aes' : 'plain') : null,
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
