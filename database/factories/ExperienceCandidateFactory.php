<?php

namespace Database\Factories;

use App\Enums\ExperienceCandidateStatus;
use App\Models\ExperienceCandidate;
use App\Models\ExperienceExtraction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExperienceCandidate>
 */
class ExperienceCandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'extraction_id' => ExperienceExtraction::factory(),
            'question' => fake()->sentence().'？',
            'similar_questions' => [fake()->sentence().'？'],
            'answer' => fake()->paragraph(),
            'source_conversation_ids' => [],
            'conversation_count' => 1,
            'status' => ExperienceCandidateStatus::Pending,
            'adopted_qa_entry_id' => null,
            'handled_by_user_id' => null,
            'handled_at' => null,
        ];
    }
}
