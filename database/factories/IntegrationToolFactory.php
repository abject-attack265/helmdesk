<?php

namespace Database\Factories;

use App\Models\Integration;
use App\Models\IntegrationTool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IntegrationTool>
 */
class IntegrationToolFactory extends Factory
{
    /**
     * 一个最小可用工具的默认状态：启用、已 last_seen，schema 为简单字符串入参。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'integration_id' => Integration::factory(),
            'name' => $name,
            'description' => fake()->sentence(),
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'query' => ['type' => 'string'],
                ],
                'required' => ['query'],
            ],
            'annotations' => null,
            'is_enabled' => true,
            'last_seen_at' => now(),
            'removed_at' => null,
        ];
    }

    /**
     * 标记为已下线工具。
     */
    public function removed(): self
    {
        return $this->state([
            'is_enabled' => false,
            'removed_at' => now(),
        ]);
    }
}
