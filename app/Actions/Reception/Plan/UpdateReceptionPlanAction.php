<?php

namespace App\Actions\Reception\Plan;

use App\Data\Reception\Config\ReceptionStrategyConfigData;
use App\Data\Reception\Form\FormUpdateReceptionPlanData;
use App\Data\Reception\ServiceScenario\PlanIntegrationGrantData;
use App\Exceptions\BusinessException;
use App\Models\Integration;
use App\Models\KnowledgeBase;
use App\Models\ReceptionPlan;
use App\Services\KnowledgeBase\KnowledgeBaseReferenceLock;
use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 保存接待方案的基础信息、接待设置、知识库和集成，并发布可供接待使用的版本。
 */
class UpdateReceptionPlanAction
{
    use AsAction;

    /**
     * 注入知识库引用锁和接待方案版本发布流程。
     */
    public function __construct(
        private readonly EnsureReceptionPlanVersionAction $ensureReceptionPlanVersion,
        private readonly KnowledgeBaseReferenceLock $referenceLock,
    ) {}

    /**
     * 保存名称唯一的方案及其关联配置。
     */
    public function handle(ReceptionPlan $plan, FormUpdateReceptionPlanData $data): void
    {
        $name = trim($data->name);
        $this->ensureNameIsAvailable($plan, $name);

        $knowledgeBaseIds = array_values(array_unique($data->knowledge_base_ids));

        $grants = $this->buildIntegrationGrants($data->integration_grants);
        $this->assertIntegrationIdsExist(array_keys($grants));

        $strategyConfig = ReceptionStrategyConfigData::fromArray($data->strategy_config)->toConfigArray();

        try {
            $this->referenceLock->run(
                $knowledgeBaseIds,
                function (Closure $refreshLock) use ($plan, $name, $data, $strategyConfig, $knowledgeBaseIds, $grants): void {
                    $this->assertKnowledgeBaseIdsExist($knowledgeBaseIds);
                    $refreshLock();

                    DB::transaction(function () use ($plan, $name, $data, $strategyConfig, $knowledgeBaseIds, $grants): void {
                        $plan->update([
                            'name' => $name,
                            'description' => filled($data->description) ? $data->description : null,
                            'persona_config' => [
                                'display_name' => $data->persona_display_name,
                                'tone' => $data->persona_tone,
                            ],
                            'global_instructions' => $data->global_instructions,
                            'strategy_config' => $strategyConfig,
                        ]);

                        $plan->knowledgeBases()->sync($knowledgeBaseIds);
                        $this->syncIntegrationGrants($plan, $grants);
                    });
                },
            );
        } catch (LockTimeoutException $exception) {
            Log::warning('接待方案保存等待知识库操作超时。', [
                'reception_plan_id' => (string) $plan->id,
                'knowledge_base_ids' => $knowledgeBaseIds,
            ]);

            throw new BusinessException(
                __('knowledge_base.messages.operation_busy'),
                previous: $exception,
            );
        }

        $this->ensureReceptionPlanVersion->handle($plan->refresh(), Auth::user());
    }

    /**
     * 校验编辑表单并返回当前方案详情页。
     */
    public function asController(Request $request, string $plan): RedirectResponse
    {
        $planModel = ReceptionPlan::query()
            ->findOrFail($plan);

        $this->handle($planModel, FormUpdateReceptionPlanData::from($request));

        return redirect()->route('app.manage.reception.plans.show', [
            'plan' => $planModel->id,
        ]);
    }

    /**
     * 检查除当前方案外的活跃或已删除方案是否占用名称。
     */
    private function ensureNameIsAvailable(ReceptionPlan $plan, string $name): void
    {
        $exists = ReceptionPlan::query()
            ->withTrashed()
            ->where('name', $name)
            ->whereKeyNot($plan->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => __('reception.messages.plan_name_exists'),
            ]);
        }
    }

    /**
     * 确认方案要使用的知识库存在。
     *
     * @param  list<string>  $knowledgeBaseIds
     */
    private function assertKnowledgeBaseIdsExist(array $knowledgeBaseIds): void
    {
        if ($knowledgeBaseIds === []) {
            return;
        }

        $validCount = KnowledgeBase::query()
            ->useWritePdo()
            ->whereIn('id', $knowledgeBaseIds)
            ->count();

        if ($validCount !== count($knowledgeBaseIds)) {
            throw ValidationException::withMessages([
                'knowledge_base_ids' => __('reception.messages.knowledge_base_invalid'),
            ]);
        }
    }

    /**
     * 合并同一集成的工具授权并去除空工具名。
     *
     * @param  iterable<PlanIntegrationGrantData>  $rawGrants
     * @return array<string, list<string>>
     */
    private function buildIntegrationGrants(iterable $rawGrants): array
    {
        $grants = [];
        foreach ($rawGrants as $raw) {
            $toolNames = [];
            foreach ($raw->tool_names as $name) {
                $trimmed = trim($name);
                if ($trimmed !== '') {
                    $toolNames[$trimmed] = true;
                }
            }

            $existing = $grants[$raw->integration_id] ?? [];
            $grants[$raw->integration_id] = array_values(array_unique([
                ...$existing,
                ...array_keys($toolNames),
            ]));
        }

        return $grants;
    }

    /**
     * 同步方案的集成授权，空工具清单表示允许全部已启用工具。
     *
     * @param  array<string, list<string>>  $grants
     */
    private function syncIntegrationGrants(ReceptionPlan $plan, array $grants): void
    {
        $plan->integrationGrants()
            ->whereNotIn('integration_id', array_keys($grants))
            ->delete();

        foreach ($grants as $integrationId => $toolNames) {
            $plan->integrationGrants()->updateOrCreate(
                ['integration_id' => $integrationId],
                ['tool_whitelist' => $toolNames === [] ? null : $toolNames],
            );
        }
    }

    /**
     * 确认方案要使用的集成存在。
     *
     * @param  list<string>  $integrationIds
     */
    private function assertIntegrationIdsExist(array $integrationIds): void
    {
        if ($integrationIds === []) {
            return;
        }

        $validCount = Integration::query()
            ->whereIn('id', $integrationIds)
            ->count();

        if ($validCount !== count($integrationIds)) {
            throw new BusinessException(__('reception.messages.integration_invalid'));
        }
    }
}
