<?php

namespace App\Data\Experience;

use App\Enums\ExperienceCandidateStatus;
use App\Models\ExperienceCandidate;
use Spatie\LaravelData\Data;

/**
 * 候选经验列表项，用于 resources/js/pages/experiences/Index.vue 的候选列表与采纳对话框回填。
 */
class ListExperienceCandidateItemData extends Data
{
    public function __construct(
        public string $id,
        public string $question,
        /** @var string[] */
        public array $similar_questions,
        public string $answer,
        public int $conversation_count,
        /** @var string[] */
        public array $source_conversation_ids,
        public ExperienceCandidateStatus $status,
        public string $status_label,
        public ?string $adopted_qa_entry_id,
        public string $created_at,
    ) {}

    /**
     * 从模型构造列表项。
     */
    public static function fromModel(ExperienceCandidate $candidate): self
    {
        return new self(
            id: (string) $candidate->id,
            question: (string) $candidate->question,
            similar_questions: array_values($candidate->similar_questions),
            answer: (string) $candidate->answer,
            conversation_count: $candidate->conversation_count,
            source_conversation_ids: array_values($candidate->source_conversation_ids),
            status: $candidate->status,
            status_label: $candidate->status->label(),
            adopted_qa_entry_id: $candidate->adopted_qa_entry_id !== null ? (string) $candidate->adopted_qa_entry_id : null,
            created_at: $candidate->created_at?->toIso8601String() ?? '',
        );
    }
}
