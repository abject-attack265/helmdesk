<?php

namespace App\Data\Reception\ServiceScenario;

use App\Models\IntegrationTool;
use Spatie\LaravelData\Data;

/**
 * 接待方案集成授权里某个集成下的可选工具项。
 * 作为 PlanIntegrationOptionData.tools 的元素，供方案级集成授权多选组件勾选工具白名单。
 */
class PlanIntegrationToolOptionData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
    ) {}

    /**
     * 从已启用且未下线的 IntegrationTool 行构造选项。
     */
    public static function fromModel(IntegrationTool $tool): self
    {
        return new self(
            name: $tool->name,
            description: filled($tool->description) ? $tool->description : null,
        );
    }
}
