<?php

namespace Database\Factories;

use App\Models\KnowledgeQaEntry;
use App\Models\KnowledgeQaQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeQaQuestion>
 */
class KnowledgeQaQuestionFactory extends Factory
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
            'question' => fake()->unique()->sentence(),
            'sort_order' => 0,
        ];
    }
}
