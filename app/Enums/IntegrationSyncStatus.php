<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 集成（Integration）最近一次同步工具列表的结果状态。
 * 用于集成详情页头的状态文字和重试入口。
 */
enum IntegrationSyncStatus: string implements LabeledEnum
{
    case Pending = 'pending';
    case Syncing = 'syncing';
    case Success = 'success';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('integration.sync_statuses.pending'),
            self::Syncing => __('integration.sync_statuses.syncing'),
            self::Success => __('integration.sync_statuses.success'),
            self::Failed => __('integration.sync_statuses.failed'),
        };
    }
}
