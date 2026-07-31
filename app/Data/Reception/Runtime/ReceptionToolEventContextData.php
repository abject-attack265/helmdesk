<?php

namespace App\Data\Reception\Runtime;

use Spatie\LaravelData\Data;

/**
 * 单次接待模型尝试的工具事件记录上下文。
 */
class ReceptionToolEventContextData extends Data
{
    /**
     * 创建单次接待工具事件上下文。
     *
     * @param  list<ReceptionToolEventDefinitionData>  $definitions
     */
    public function __construct(
        public string $conversation_id,
        public string $turn_id,
        public array $definitions,
    ) {}

    /**
     * 查找运行时工具对应的事件定义。
     */
    public function definitionFor(string $toolName): ?ReceptionToolEventDefinitionData
    {
        foreach ($this->definitions as $definition) {
            if ($definition->tool_name === $toolName) {
                return $definition;
            }
        }

        return null;
    }
}
