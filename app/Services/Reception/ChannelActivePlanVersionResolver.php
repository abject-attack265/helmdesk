<?php

namespace App\Services\Reception;

use App\Enums\ReceptionPlanVersionStatus;
use App\Models\Channel;
use App\Models\ReceptionPlanVersion;

/**
 * 把「渠道当前生效的接待方案版本 = 所绑方案的最新已发布版本」收口成单一解析逻辑。
 *
 * 渠道绑定 reception_plan_id，运行时自动跟随该方案最新版；进行中会话已锁定各自版本不受影响。
 * 所有「由渠道拿版本」的消费点统一调用本服务，保证跟随最新版的正确性只有一个来源。
 *
 * 命名相近的三件套，按返回物区分：本服务取版本对象本身；
 * 取该版本的可用状态见 ChannelReceptionPlanVersionResolver，取其策略配置见 ReceptionPlanStrategyResolver。
 */
class ChannelActivePlanVersionResolver
{
    /**
     * 返回渠道当前绑定方案的最新已发布版本；未绑方案或方案无已发布版本时返回 null。
     */
    public function currentVersionForChannel(Channel $channel): ?ReceptionPlanVersion
    {
        if (! filled($channel->reception_plan_id)) {
            return null;
        }

        return $this->latestPublishedVersionForPlan((string) $channel->reception_plan_id);
    }

    /**
     * 返回指定方案的最新已发布版本；方案无已发布版本时返回 null。
     */
    public function latestPublishedVersionForPlan(string $planId): ?ReceptionPlanVersion
    {
        return ReceptionPlanVersion::query()
            ->where('reception_plan_id', $planId)
            ->where('status', ReceptionPlanVersionStatus::Published)
            ->orderByDesc('version_number')
            ->first();
    }
}
