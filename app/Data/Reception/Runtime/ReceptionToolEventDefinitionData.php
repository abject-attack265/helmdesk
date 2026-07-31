<?php

namespace App\Data\Reception\Runtime;

use Spatie\LaravelData\Data;

/**
 * 接待工具事件的展示来源快照。
 */
class ReceptionToolEventDefinitionData extends Data
{
    /**
     * 创建工具事件展示来源快照。
     *
     * @param  list<string>  $source_names
     */
    public function __construct(
        public string $tool_name,
        public string $source_type,
        public array $source_names,
        public ?string $description,
    ) {}
}
