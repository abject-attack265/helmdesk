<?php

namespace App\Data\Reception;

use App\Data\Reception\Config\PersonaConfigData;
use App\Data\Reception\Config\ReceptionStrategyConfigData;
use App\Data\Reception\ServiceScenario\PlanIntegrationGrantData;
use App\Models\ReceptionPlan;
use App\Models\ReceptionPlanIntegrationGrant;
use Spatie\LaravelData\Data;

/**
 * 接待方案展示数据。
 * 由 ShowReceptionPlanIndexPageAction 组装后下发给 resources/js/pages/reception/plans/Index.vue，
 * 同时支撑左侧列表行 / 详情区基础信息使用。
 * 版本快照对运营隐藏（保存即发布、渠道自动跟随最新版），此处不下发版本信息。
 * 模型运行时按用途从全局池取用，此处不下发模型信息。
 * knowledge_base_ids / integration_grants 字段仅"活跃 view 中的当前选中 plan"才填充完整内容，
 * 其它列表行（含 trash 视图）下保持为空数组以减小 payload。
 */
class ReceptionPlanData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public PersonaConfigData $persona_config,
        public ?string $global_instructions,
        public int $knowledge_bases_count,
        public int $integration_grants_count,
        public ?string $updated_at,
        public ?string $deleted_at,
        public ReceptionStrategyConfigData $strategy_config,
        /** @var list<string> */
        public array $knowledge_base_ids = [],
        /** @var list<PlanIntegrationGrantData> */
        public array $integration_grants = [],
    ) {}

    /**
     * 从 Eloquent 模型组装精简版展示数据（左侧列表 / 回收站行用）。
     */
    public static function fromModel(ReceptionPlan $plan): self
    {
        return new self(
            id: (string) $plan->id,
            name: $plan->name,
            description: filled($plan->description) ? $plan->description : null,
            persona_config: PersonaConfigData::fromArray($plan->persona_config),
            global_instructions: filled($plan->global_instructions) ? $plan->global_instructions : null,
            knowledge_bases_count: (int) ($plan->knowledge_bases_count ?? $plan->knowledgeBases()->count()),
            integration_grants_count: (int) ($plan->integration_grants_count ?? $plan->integrationGrants()->count()),
            updated_at: $plan->updated_at?->toIso8601String(),
            deleted_at: $plan->deleted_at?->toIso8601String(),
            strategy_config: ReceptionStrategyConfigData::fromArray($plan->strategy_config),
        );
    }

    /**
     * 在精简数据基础上补全 knowledge_base_ids / integration_grants，
     * 供当前选中 plan 的详情区使用。整数授权从关系导出。
     */
    public static function fromModelDetailed(ReceptionPlan $plan): self
    {
        $base = self::fromModel($plan);

        $base->knowledge_base_ids = $plan->knowledgeBases()
            ->pluck('knowledge_bases.id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        $base->integration_grants = $plan->integrationGrants()
            ->get()
            ->map(static fn (ReceptionPlanIntegrationGrant $grant): PlanIntegrationGrantData => new PlanIntegrationGrantData(
                integration_id: (string) $grant->integration_id,
                tool_names: is_array($grant->tool_whitelist) ? array_values($grant->tool_whitelist) : [],
            ))
            ->all();

        return $base;
    }
}
