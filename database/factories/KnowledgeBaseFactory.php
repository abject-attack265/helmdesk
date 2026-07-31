<?php

namespace Database\Factories;

use App\Enums\KnowledgeBaseCategory;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeBase>
 */
class KnowledgeBaseFactory extends Factory
{
    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (KnowledgeBase $knowledgeBase): void {
            KnowledgeGroup::query()->create([
                'knowledge_base_id' => $knowledgeBase->id,
                'parent_id' => null,
                'name' => KnowledgeBase::DEFAULT_GROUP_NAME,
                'is_default' => true,
                'sort_order' => 0,
            ]);
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'avatar_id' => null,
            'description' => fake()->sentence(),
        ];
    }

    /**
     * 问答库。
     */
    public function qa(): static
    {
        return $this->state(fn (): array => ['category' => KnowledgeBaseCategory::Qa]);
    }
}
