<?php

namespace App\Services\Reception;

use App\Data\Reception\Config\ReceptionStrategyConfigData;
use App\Models\Channel;
use LogicException;

/**
 * 从渠道绑定方案的最新已发布版本读取流程策略。
 *
 * 命名相近的三件套，按返回物区分：本服务取版本的策略配置（ReceptionStrategyConfigData）；
 * 取版本对象本身见 ChannelActivePlanVersionResolver，取其可用状态见 ChannelReceptionPlanVersionResolver。
 */
class ReceptionPlanStrategyResolver
{
    /**
     * 注入版本解析器以获取渠道绑定方案的最新已发布版本。
     */
    public function __construct(
        private readonly ChannelActivePlanVersionResolver $activePlanVersionResolver,
    ) {}

    /**
     * 返回渠道绑定方案最新已发布版本中的接待策略配置。
     */
    public function forChannel(Channel $channel): ReceptionStrategyConfigData
    {
        $version = $this->activePlanVersionResolver->currentVersionForChannel($channel);

        if ($version === null) {
            throw new LogicException('Web channel must reference a reception plan with a published version.');
        }

        return $version->strategyConfig();
    }
}
