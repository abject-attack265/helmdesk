<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 接待方案中接待人设的语气风格，作为 system prompt 中的 Persona 输入项。
 */
enum ReceptionPersonaTone: string implements LabeledEnum
{
    case Professional = 'professional';
    case Friendly = 'friendly';
    case Concise = 'concise';

    public function label(): string
    {
        return match ($this) {
            self::Professional => __('reception.persona_tones.professional'),
            self::Friendly => __('reception.persona_tones.friendly'),
            self::Concise => __('reception.persona_tones.concise'),
        };
    }
}
