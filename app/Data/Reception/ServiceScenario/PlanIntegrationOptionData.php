<?php

namespace App\Data\Reception\ServiceScenario;

use App\Models\Integration;
use Spatie\LaravelData\Data;

/**
 * 接待方案级集成授权多选项数据。
 * 由 ShowReceptionPlanDetailPageAction 一次性下发给 Detail.vue，
 * 用于方案级集成授权配置：勾选集成，并可在每个集成下进一步勾选工具白名单（不勾=全部已启用工具）。
 */
class PlanIntegrationOptionData extends Data
{
    /**
     * @param  list<PlanIntegrationToolOptionData>  $tools  该集成当前已启用且未下线的工具
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $provider_label,
        public array $tools,
    ) {}

    /**
     * 从已加载 tools 关系（应预过滤为启用且未下线）的 Integration 行构造选项。
     */
    public static function fromModel(Integration $integration): self
    {
        return new self(
            id: (string) $integration->id,
            name: (string) $integration->name,
            provider_label: $integration->provider->label(),
            tools: $integration->tools
                ->map(fn ($tool): PlanIntegrationToolOptionData => PlanIntegrationToolOptionData::fromModel($tool))
                ->all(),
        );
    }
}
