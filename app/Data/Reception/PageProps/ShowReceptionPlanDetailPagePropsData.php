<?php

namespace App\Data\Reception\PageProps;

use App\Data\EnumOptionData;
use App\Data\Reception\ReceptionPlanData;
use App\Data\Reception\ServiceScenario\PlanIntegrationOptionData;
use App\Data\Reception\ServiceScenario\PlanKnowledgeBaseOptionData;
use Spatie\LaravelData\Data;

/**
 * 接待方案详情（编辑）页 props。
 * 由 ShowReceptionPlanDetailPageAction 返回，下发给 resources/js/pages/reception/plans/Detail.vue。
 * 承载单个方案的完整配置（含服务场景 / 方案级 KB / MCP 工具）及表单所需的全部选项。
 * 保存即发布：编辑保存自动产出版本快照，版本对运营隐藏。
 * 模型运行时按用途从全局池取用。
 *
 * knowledge_base_options / integration_options 用于关联知识库 / 集成授权的多选项。
 */
class ShowReceptionPlanDetailPagePropsData extends Data
{
    public function __construct(
        public ReceptionPlanData $plan,
        /** @var EnumOptionData[] */
        public array $persona_tone_options,
        /** @var PlanKnowledgeBaseOptionData[] */
        public array $knowledge_base_options,
        /** @var PlanIntegrationOptionData[] */
        public array $integration_options,
    ) {}
}
