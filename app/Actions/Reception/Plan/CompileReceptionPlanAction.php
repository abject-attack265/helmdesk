<?php

namespace App\Actions\Reception\Plan;

use App\Data\Reception\Plan\CompiledIntegrationGrantData;
use App\Data\Reception\Plan\CompiledKnowledgeBaseData;
use App\Data\Reception\Plan\CompiledReceptionPlanData;
use App\Data\Reception\Plan\ReceptionPlanCompiledConfigData;
use App\Enums\ReceptionPersonaTone;
use App\Exceptions\BusinessException;
use App\Models\ReceptionPlan;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 将接待方案草稿编译为版本快照和运行时配置。
 *
 * 业务人设、知识库和集成授权进入版本快照；操作规则、工具集和模型在运行时解析。
 */
class CompileReceptionPlanAction
{
    use AsAction;

    /**
     * 编译草稿，输出 snapshot（原貌）与 compiled（运行时形态）两份配置。
     */
    public function handle(ReceptionPlan $plan): CompiledReceptionPlanData
    {
        $personaConfig = $plan->persona_config ?? [];
        $strategyConfig = $plan->strategy_config;

        $kbSnapshots = $this->loadKnowledgeBaseSnapshots($plan);
        $grantSnapshots = $this->loadIntegrationGrantSnapshots($plan);

        $knowledgeBaseIds = array_map(
            static fn (CompiledKnowledgeBaseData $kb): string => $kb->id,
            $kbSnapshots,
        );
        $integrationGrants = array_map(
            static fn (CompiledIntegrationGrantData $grant): array => [
                'integration_id' => $grant->integration_id,
                'tool_names' => $grant->tool_names,
            ],
            $grantSnapshots,
        );

        $snapshotConfig = [
            'name' => $plan->name,
            'description' => $plan->description,
            'persona_config' => $personaConfig,
            'global_instructions' => $plan->global_instructions,
            'knowledge_base_ids' => $knowledgeBaseIds,
            'integration_grants' => $integrationGrants,
            'strategy_config' => $strategyConfig,
        ];

        $compiledConfig = new ReceptionPlanCompiledConfigData(
            reception_instruction: $this->buildReceptionInstruction($personaConfig, $plan->global_instructions),
            knowledge_bases: $kbSnapshots,
            integration_grants: $grantSnapshots,
        );

        return new CompiledReceptionPlanData(
            snapshot_config: $snapshotConfig,
            compiled_config: $compiledConfig,
        );
    }

    /**
     * 从方案的知识库关系加载 KB 快照，验证所有 KB 仍在应用内可见。
     * description 透传给 knowledge_search 工具，让工具描述里能体现每个 KB 的用途。
     *
     * @return list<CompiledKnowledgeBaseData>
     */
    private function loadKnowledgeBaseSnapshots(ReceptionPlan $plan): array
    {
        // 直接数 pivot 行，避免 BelongsToMany join 把悬空 pivot 隐藏掉。
        $pivotCount = DB::table('reception_plan_knowledge_bases')
            ->where('reception_plan_id', $plan->id)
            ->count();

        $knowledgeBases = $plan->knowledgeBases()
            ->get(['knowledge_bases.id', 'knowledge_bases.name', 'knowledge_bases.description', 'knowledge_bases.category']);

        // pivot 指向已不存在的 KB（悬空）时关系 join 不会返回，数量不一致即视为不可用。
        if ($knowledgeBases->count() !== $pivotCount) {
            throw new BusinessException(__('reception.messages.knowledge_base_invalid'));
        }

        $snapshots = [];
        foreach ($knowledgeBases as $kb) {
            $snapshots[] = CompiledKnowledgeBaseData::fromModel($kb);
        }

        return $snapshots;
    }

    /**
     * 从方案的集成授权关系加载授权快照，验证每个授权的 integration 仍属当前应用。
     *
     * @return list<CompiledIntegrationGrantData>
     */
    private function loadIntegrationGrantSnapshots(ReceptionPlan $plan): array
    {
        $grants = $plan->integrationGrants()->with('integration')->get();

        $snapshots = [];
        foreach ($grants as $grant) {
            $integration = $grant->integration;
            if ($integration === null) {
                throw new BusinessException(__('reception.messages.integration_invalid'));
            }
            $snapshots[] = CompiledIntegrationGrantData::fromGrant($grant);
        }

        return $snapshots;
    }

    /**
     * 把 Persona 头部 + 全局指引拼成版本化的 system prompt 基底。
     * 单 Agent 操作规则由运行时拼接。
     *
     * @param  array<string, mixed>  $personaConfig
     */
    private function buildReceptionInstruction(array $personaConfig, ?string $globalInstructions): string
    {
        $segments = [];

        $displayName = (string) $personaConfig['display_name'];
        $tone = ReceptionPersonaTone::from((string) $personaConfig['tone'])->label();

        $segments[] = implode("\n", [
            '[Persona]',
            "你的名字是 {$displayName}。",
            "保持 {$tone} 的语气与访客交流。",
        ]);

        if (filled($globalInstructions)) {
            $segments[] = "[全局指引]\n".$globalInstructions;
        }

        return implode("\n\n", $segments);
    }
}
