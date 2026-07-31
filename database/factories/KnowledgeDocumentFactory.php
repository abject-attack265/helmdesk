<?php

namespace Database\Factories;

use App\Enums\KnowledgeDocumentStatus;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeDocument>
 */
class KnowledgeDocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->sentence(3);
        $filename = $name.'.md';
        $content = "# {$name}\n\n".fake()->paragraph();

        return [
            'knowledge_base_id' => KnowledgeBase::factory(),
            'group_id' => fn (array $attributes) => KnowledgeGroup::query()
                ->where('knowledge_base_id', $attributes['knowledge_base_id'])
                ->where('is_default', true)
                ->value('id'),
            'uploaded_by_user_id' => null,
            'original_filename' => $filename,
            'mime_type' => 'text/markdown',
            'byte_size' => strlen($content),
            'extension' => 'md',
            'checksum_sha256' => hash('sha256', $content),
            'source_type' => 'upload',
            'status' => KnowledgeDocumentStatus::Pending,
            'error_message' => null,
            'content' => $content,
        ];
    }
}
