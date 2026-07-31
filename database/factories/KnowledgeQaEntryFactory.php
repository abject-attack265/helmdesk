<?php

namespace Database\Factories;

use App\Enums\KnowledgeBaseCategory;
use App\Enums\KnowledgeDocumentIndexingStatus;
use App\Enums\KnowledgeQaEntryStatus;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeGroup;
use App\Models\KnowledgeQaEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeQaEntry>
 */
class KnowledgeQaEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'knowledge_base_id' => KnowledgeBase::factory()->state([
                'category' => KnowledgeBaseCategory::Qa->value,
            ]),
            'group_id' => fn (array $attributes) => KnowledgeGroup::query()
                ->where('knowledge_base_id', $attributes['knowledge_base_id'])
                ->where('is_default', true)
                ->value('id'),
            'created_by_user_id' => null,
            'question' => fake()->unique()->sentence(),
            'status' => KnowledgeQaEntryStatus::Indexed,
            'error_message' => null,
            'vector_status' => KnowledgeDocumentIndexingStatus::Idle,
            'vector_error' => null,
            'vector_indexed_at' => null,
            'sort_order' => 0,
        ];
    }
}
