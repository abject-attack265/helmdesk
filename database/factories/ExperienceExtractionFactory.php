<?php

namespace Database\Factories;

use App\Enums\ExperienceExtractionStatus;
use App\Models\ExperienceExtraction;
use App\Models\KnowledgeBase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExperienceExtraction>
 */
class ExperienceExtractionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // 默认补一个绑定的问答库；测试可显式覆盖为指定库。
            'knowledge_base_id' => fn (): string => (string) KnowledgeBase::factory()->qa()->create()->id,
            'triggered_by_user_id' => null,
            'status' => ExperienceExtractionStatus::Completed,
            'scanned_from' => null,
            'scanned_until' => now(),
            'conversation_count' => 0,
            'candidate_count' => 0,
            'error' => null,
        ];
    }

    /**
     * 进行中的运行。
     */
    public function running(): static
    {
        return $this->state(fn (): array => ['status' => ExperienceExtractionStatus::Running]);
    }

    /**
     * 失败的运行。
     */
    public function failed(): static
    {
        return $this->state(fn (): array => [
            'status' => ExperienceExtractionStatus::Failed,
            'error' => 'model unavailable',
        ]);
    }
}
