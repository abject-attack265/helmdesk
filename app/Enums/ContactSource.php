<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 联系人来源，记录联系人最初来自哪个入口。
 */
enum ContactSource: string implements LabeledEnum
{
    case Web = 'web';
    case Email = 'email';
    case Api = 'api';
    case Manual = 'manual';
    case Telegram = 'telegram';
    case WechatOfficialAccount = 'wechat_oa';

    public function label(): string
    {
        return match ($this) {
            self::Web => __('contact.sources.web'),
            self::Email => __('contact.sources.email'),
            self::Api => __('contact.sources.api'),
            self::Manual => __('contact.sources.manual'),
            self::Telegram => __('contact.sources.telegram'),
            self::WechatOfficialAccount => __('contact.sources.wechat_official_account'),
        };
    }
}
