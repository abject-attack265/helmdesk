<?php

namespace App\Actions\Reception\Plan;

use App\Enums\AiModelPurpose;
use App\Exceptions\BusinessException;
use App\Models\ReceptionPlan;
use App\Services\AiRuntime\AiModelPool;
use App\Services\Reception\ChannelActivePlanVersionResolver;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 解析渠道要绑定的接待方案，统一校验同应用 + 最新版本可用。
 * 渠道绑方案后自动跟随其最新已发布版本，因此校验对象是「方案的最新版」而非具体版本。
 */
class ResolveChannelReceptionPlanAction
{
    use AsAction;

    /**
     * 注入版本解析器与 AI 模型解析器以校验方案最新版可用。
     */
    public function __construct(
        private readonly ChannelActivePlanVersionResolver $activePlanVersionResolver,
        private readonly AiModelPool $modelPool,
    ) {}

    /**
     * 校验并返回归一化后的接待方案 ID；方案不存在或无可用最新版本时抛业务异常。
     */
    public function handle(string $planId, bool $requireUsable = true): string
    {
        $plan = ReceptionPlan::query()

            ->find($planId);

        if ($plan === null) {
            throw new BusinessException(__('channel.messages.invalid_reception_plan'));
        }

        if (! $requireUsable) {
            return (string) $plan->id;
        }

        $version = $this->activePlanVersionResolver->latestPublishedVersionForPlan((string) $plan->id);

        if ($version === null) {
            throw new BusinessException(__('channel.messages.reception_plan_no_usable_version'));
        }

        if (! $this->modelPool->hasUsable(AiModelPurpose::ReceptionChat)) {
            throw new BusinessException(__('channel.messages.reception_plan_version_model_unavailable'));
        }

        return (string) $plan->id;
    }
}
