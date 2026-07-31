<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 联系人身份标识类型，如邮箱、手机号、会话 token。
 *
 * ExternalId 是业务方主动传入的用户标识（网页身份令牌 sub、网关转发身份头），
 * 应用内同值即同一联系人，面板/工具按它与业务系统关联；ChannelAccount 是渠道
 * 自身的技术标识（如 Telegram 用户 ID），仅用于渠道识别回访访客，二者不可混用。
 */
enum IdentityType: string implements LabeledEnum
{
    case Session = 'session';
    case Email = 'email';
    case Phone = 'phone';
    case ExternalId = 'external_id';
    case ChannelAccount = 'channel_account';

    public function label(): string
    {
        return match ($this) {
            self::Session => __('contact.identity_types.session'),
            self::Email => __('contact.identity_types.email'),
            self::Phone => __('contact.identity_types.phone'),
            self::ExternalId => __('contact.identity_types.external_id'),
            self::ChannelAccount => __('contact.identity_types.channel_account'),
        };
    }

    public function requiresNamespace(): bool
    {
        return match ($this) {
            self::ChannelAccount => true,
            default => false,
        };
    }

    public function supportsManualManagement(): bool
    {
        return match ($this) {
            self::Email, self::Phone => true,
            default => false,
        };
    }
}
