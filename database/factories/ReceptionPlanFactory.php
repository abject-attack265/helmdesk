<?php

namespace Database\Factories;

use App\Data\Reception\Config\ReceptionStrategyConfigData;
use App\Models\ReceptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReceptionPlan>
 */
class ReceptionPlanFactory extends Factory
{
    /**
     * Plan 草稿的默认状态：含基本人设、空服务场景，知识库 / 集成授权通过关系表单独建。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' 接待方案',
            'description' => fake()->sentence(),
            'persona_config' => [
                'display_name' => fake()->firstName().' 助手',
                'tone' => fake()->randomElement(['professional', 'friendly', 'concise']),
            ],
            'global_instructions' => fake()->paragraph(),
            'strategy_config' => ReceptionStrategyConfigData::defaultConfig(),
        ];
    }
}
