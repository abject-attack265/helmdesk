<?php

namespace App\Data\Reception;

use Spatie\LaravelData\Data;

/**
 * 接待会话的聚合活动状态，供访客窗口和收件箱展示实时处理提示。
 */
class ReceptionActivityStateData extends Data
{
    /**
     * 承载活动标记、剩余租期与单调版本。
     */
    public function __construct(
        public bool $active,
        public int $hold_ms,
        public int $revision,
    ) {}

    /**
     * 返回无接待方活动的状态。
     */
    public static function inactive(int $revision): self
    {
        return new self(active: false, hold_ms: 0, revision: $revision);
    }
}
