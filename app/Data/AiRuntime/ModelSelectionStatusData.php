<?php

namespace App\Data\AiRuntime;

use Spatie\LaravelData\Data;

/**
 * 模型选中项状态数据。
 * 由后端组装后传给接待方案版本编辑等模型选择界面，
 * 用于展示当前模型引用是否仍然有效及失效原因。
 */
class ModelSelectionStatusData extends Data
{
    public function __construct(
        public ?string $id,
        public ?string $label,
        public bool $is_valid,
        public ?string $reason = null,
        public ?string $reason_label = null,
    ) {}
}
