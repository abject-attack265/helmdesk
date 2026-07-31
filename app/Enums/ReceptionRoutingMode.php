<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 接待方案的首接路由策略。
 */
enum ReceptionRoutingMode: string implements LabeledEnum
{
    case AiFirst = 'ai_first';
    case TeammateFirst = 'teammate_first';

    public function label(): string
    {
        return match ($this) {
            self::AiFirst => __('reception.routing_modes.ai_first'),
            self::TeammateFirst => __('reception.routing_modes.teammate_first'),
        };
    }
}
