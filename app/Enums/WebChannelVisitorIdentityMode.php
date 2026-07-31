<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 网站渠道访客侧消息身份展示方式。
 */
enum WebChannelVisitorIdentityMode: string implements LabeledEnum
{
    case ActualReceptionist = 'actual_receptionist';
    case UnifiedService = 'unified_service';

    public function label(): string
    {
        return match ($this) {
            self::ActualReceptionist => __('channel.web_visitor_identity_modes.actual_receptionist'),
            self::UnifiedService => __('channel.web_visitor_identity_modes.unified_service'),
        };
    }
}
