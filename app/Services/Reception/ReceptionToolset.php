<?php

namespace App\Services\Reception;

use App\Data\Reception\Runtime\ReceptionToolEventDefinitionData;
use NeuronAI\Tools\Tool;

/**
 * 接待 Agent 的运行时工具集及其可展示事件定义。
 */
final readonly class ReceptionToolset
{
    /**
     * 创建运行时工具集及其事件定义。
     *
     * @param  list<Tool>  $tools
     * @param  list<ReceptionToolEventDefinitionData>  $event_definitions
     */
    public function __construct(
        public array $tools,
        public array $event_definitions,
    ) {}
}
