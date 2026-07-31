<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 经验提炼运行的状态：触发即 Running，LLM 扫描完成为 Completed，异常终止为 Failed。
 */
enum ExperienceExtractionStatus: string implements LabeledEnum
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Running => __('experience.extraction_statuses.running'),
            self::Completed => __('experience.extraction_statuses.completed'),
            self::Failed => __('experience.extraction_statuses.failed'),
        };
    }
}
