<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 接待方案版本生命周期状态。PlanVersion 在发布时落行，不存在草稿态。
 *
 * - Published：已发布并可被渠道部署指向。
 * - Archived：归档版本，仍可被历史会话解析，但不允许新渠道部署到该版本。
 */
enum ReceptionPlanVersionStatus: string implements LabeledEnum
{
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Published => __('reception.plan_version_statuses.published'),
            self::Archived => __('reception.plan_version_statuses.archived'),
        };
    }
}
