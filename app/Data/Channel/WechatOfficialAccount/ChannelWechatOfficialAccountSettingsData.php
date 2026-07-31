<?php

namespace App\Data\Channel\WechatOfficialAccount;

use App\Enums\ReceptionLanguage;
use Spatie\LaravelData\Data;

/** 微信公众号渠道设置。 */
class ChannelWechatOfficialAccountSettingsData extends Data
{
    /** 创建微信公众号渠道设置。 */
    public function __construct(
        public string $app_id = '',
        public string $app_secret = '',
        public string $token = '',
        public string $aes_key = '',
        public ReceptionLanguage $default_visitor_locale = ReceptionLanguage::ChineseSimplified,
        public bool $visitor_message_ai_translation_enabled = false,
        public ?string $translation_context_hint = null,
    ) {}

    /** 判断渠道是否使用安全模式。 */
    public function usesEncryption(): bool
    {
        return $this->aes_key !== '';
    }

    /** 判断渠道凭证与消息模式配置是否完整。 */
    public function isConfigured(): bool
    {
        return $this->app_id !== ''
            && $this->app_secret !== ''
            && $this->token !== ''
            && (! $this->usesEncryption()
                || (strlen($this->aes_key) === 43 && preg_match('/^[A-Za-z0-9]{43}$/', $this->aes_key) === 1));
    }
}
