<?php

namespace Database\Factories;

use App\Data\Reception\Config\ReceptionStrategyConfigData;
use App\Enums\ReceptionPlanVersionStatus;
use App\Models\ReceptionPlan;
use App\Models\ReceptionPlanVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceptionPlanVersion>
 */
class ReceptionPlanVersionFactory extends Factory
{
    /**
     * 版本的默认状态：published、version_number=1、snapshot/compiled 最小可解析快照。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $snapshot = [
            'name' => '默认接待方案',
            'persona_config' => ['display_name' => '默认助手', 'tone' => 'concise'],
            'global_instructions' => fake()->sentence(),
            'knowledge_base_ids' => [],
            'integration_grants' => [],
            'strategy_config' => ReceptionStrategyConfigData::defaultConfig(),
        ];

        return [
            'reception_plan_id' => ReceptionPlan::factory(),
            'version_number' => 1,
            'description' => null,
            'snapshot_config' => $snapshot,
            'compiled_config' => [
                'reception_instruction' => '你是一名客服助手。',
                'knowledge_bases' => [],
                'integration_grants' => [],
            ],
            'status' => ReceptionPlanVersionStatus::Published,
            'published_at' => now(),
            'published_by_user_id' => null,
        ];
    }

    /**
     * 归档状态，仍可被历史会话解析但不允许新部署指向。
     */
    public function archived(): static
    {
        return $this->state([
            'status' => ReceptionPlanVersionStatus::Archived,
        ]);
    }
}
