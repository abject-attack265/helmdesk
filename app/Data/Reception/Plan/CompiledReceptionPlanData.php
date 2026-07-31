<?php

namespace App\Data\Reception\Plan;

use Spatie\LaravelData\Data;

/**
 * CompileReceptionPlanAction 的编译结果：snapshot（草稿原貌）+ compiled（运行时形态）。
 * EnsureReceptionPlanVersionAction 据此对比最新版本并写入 ReceptionPlanVersion。
 */
class CompiledReceptionPlanData extends Data
{
    /**
     * @param  array<string, mixed>  $snapshot_config  草稿原貌快照，用于后台展示与版本对比
     */
    public function __construct(
        public array $snapshot_config,
        public ReceptionPlanCompiledConfigData $compiled_config,
    ) {}
}
