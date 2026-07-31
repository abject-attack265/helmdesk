<?php

namespace App\Data\Reception\ServiceScenario;

use Spatie\LaravelData\Data;

/**
 * 接待方案对某集成的工具授权（已选状态）。
 * 既用于 Detail.vue 表单提交（FormUpdateReceptionPlanData.integration_grants），
 * 也用于详情页回填已选授权（ReceptionPlanData.integration_grants）。
 * tool_names 为工具白名单，空数组=该集成全部已启用工具。
 */
class PlanIntegrationGrantData extends Data
{
    /**
     * @param  list<string>  $tool_names  工具名白名单（空=该集成全部已启用工具）
     */
    public function __construct(
        public string $integration_id,
        public array $tool_names = [],
    ) {}

    /**
     * 嵌套校验规则：避免 Spatie 把可空的 tool_names 推断为 required。
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'integration_id' => ['required', 'string', 'ulid'],
            'tool_names' => ['present', 'array'],
            'tool_names.*' => ['string'],
        ];
    }
}
