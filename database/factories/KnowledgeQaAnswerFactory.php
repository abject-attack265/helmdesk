<?php

namespace Database\Factories;

use App\Models\KnowledgeQaAnswer;
use App\Models\KnowledgeQaEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeQaAnswer>
 */
class KnowledgeQaAnswerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'knowledge_qa_entry_id' => KnowledgeQaEntry::factory(),
            'answer' => fake()->paragraph(),
            'is_default' => true,
            'is_enabled' => true,
            'sort_order' => 0,
        ];
    }
}
