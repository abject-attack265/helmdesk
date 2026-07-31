<?php

namespace App\Services\Reception;

use App\Data\AiRuntime\ModelSelectionStatusData;
use App\Enums\AiModelPurpose;
use App\Enums\ReceptionPlanVersionStatus;
use App\Models\Channel;
use App\Models\ReceptionPlanVersion;
use App\Services\AiRuntime\AiModelPool;

/**
 * 解析渠道绑定方案当前生效版本（最新已发布版）的可用状态。
 *
 * 统一返回 ModelSelectionStatusData 让前端复用模型状态指示器：
 * - 未绑定方案：返回 null（调用方按"未配置 AI 接待"展示）
 * - 方案无可用最新版本：is_valid=false / reason=reception_plan_no_usable_version
 * - 全局无可用接待模型：is_valid=false / reason=reception_plan_version_model_unavailable
 * - 最新版当前可用：is_valid=true
 *
 * 命名相近的三件套，按返回物区分：本服务取版本的可用状态（ModelSelectionStatusData）；
 * 取版本对象本身见 ChannelActivePlanVersionResolver，取其策略配置见 ReceptionPlanStrategyResolver。
 */
class ChannelReceptionPlanVersionResolver
{
    /**
     * 注入模型用途池与版本解析器，校验方案最新版与全局接待模型仍可用。
     */
    public function __construct(
        private readonly AiModelPool $aiModelPool,
        private readonly ChannelActivePlanVersionResolver $activePlanVersionResolver,
    ) {}

    /**
     * 解析渠道绑定方案最新已发布版本的状态；未绑定方案时返回 null。
     */
    public function resolveChannelStatus(Channel $channel): ?ModelSelectionStatusData
    {
        if (! filled($channel->reception_plan_id)) {
            return null;
        }

        $version = $this->activePlanVersionResolver->currentVersionForChannel($channel);

        if ($version === null) {
            $plan = $channel->relationLoaded('receptionPlan') ? $channel->receptionPlan : $channel->receptionPlan()->first();

            return new ModelSelectionStatusData(
                id: (string) $channel->reception_plan_id,
                label: filled($plan?->name) ? (string) $plan->name : null,
                is_valid: false,
                reason: 'reception_plan_no_usable_version',
                reason_label: __('channel.messages.reception_plan_no_usable_version'),
            );
        }

        return $this->resolveVersionStatus($version);
    }

    /**
     * 直接对版本对象做可用性解析；用于版本列表 / 选项渲染等需要复用同一规则的场景。
     */
    public function resolveVersionStatus(ReceptionPlanVersion $version): ModelSelectionStatusData
    {
        $label = $this->formatVersionLabel($version);

        if ($version->status === ReceptionPlanVersionStatus::Archived) {
            return new ModelSelectionStatusData(
                id: (string) $version->id,
                label: $label,
                is_valid: false,
                reason: 'reception_plan_version_archived',
                reason_label: __('channel.messages.reception_plan_version_archived'),
            );
        }

        if (! $this->aiModelPool->hasUsable(AiModelPurpose::ReceptionChat)) {
            return new ModelSelectionStatusData(
                id: (string) $version->id,
                label: $label,
                is_valid: false,
                reason: 'reception_plan_version_model_unavailable',
                reason_label: __('channel.messages.reception_plan_version_model_unavailable'),
            );
        }

        return new ModelSelectionStatusData(
            id: (string) $version->id,
            label: $label,
            is_valid: true,
        );
    }

    /**
     * 把版本所属方案格式化成可读 label，便于详情页头部呈现"当前接待方案：方案名"。
     * 版本号对运营已隐藏，label 只用方案名。
     */
    private function formatVersionLabel(ReceptionPlanVersion $version): string
    {
        $plan = $version->relationLoaded('plan') ? $version->plan : $version->plan()->first();

        return filled($plan?->name) ? (string) $plan->name : '';
    }
}
