<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 候选经验的处理状态：提炼产出为 Pending，管理员采纳为 Adopted（已落知识库 QA）、丢弃为 Discarded。
 */
enum ExperienceCandidateStatus: string implements LabeledEnum
{
    case Pending = 'pending';
    case Adopted = 'adopted';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('experience.candidate_statuses.pending'),
            self::Adopted => __('experience.candidate_statuses.adopted'),
            self::Discarded => __('experience.candidate_statuses.discarded'),
        };
    }
}
