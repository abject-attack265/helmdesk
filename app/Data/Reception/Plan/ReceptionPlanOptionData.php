<?php

namespace App\Data\Reception\Plan;

use Spatie\LaravelData\Data;

/**
 * 接待方案选项。
 * 由 ListReceptionPlansForChannelSelectionAction 组装后下发给渠道编辑页，供绑定「接待方案」下拉选择。
 * 渠道绑方案后自动跟随其最新已发布版本，因此 is_usable 表示「该方案最新版当前可部署」。
 */
class ReceptionPlanOptionData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $is_usable,
        public ?string $unusable_reason,
        public ?string $unusable_reason_label,
    ) {}
}
