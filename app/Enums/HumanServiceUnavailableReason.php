<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 人工服务不可用原因，用于 AI 接待路由和转人工工具结果。
 */
enum HumanServiceUnavailableReason: string implements LabeledEnum
{
    case OutsideBusinessHours = 'outside_business_hours';
    case NoOnlineTeammate = 'no_online_teammate';

    public function label(): string
    {
        return match ($this) {
            self::OutsideBusinessHours => __('reception.human_service_unavailable_reasons.outside_business_hours'),
            self::NoOnlineTeammate => __('reception.human_service_unavailable_reasons.no_online_teammate'),
        };
    }
}
