<?php

namespace App\Data\KnowledgeBase;

use App\Enums\KnowledgeDocumentIndexingStatus;
use App\Enums\KnowledgeQaEntryStatus;
use App\Models\KnowledgeQaAnswer;
use App\Models\KnowledgeQaEntry;
use App\Models\KnowledgeQaQuestion;
use Spatie\LaravelData\Data;

/**
 * 问答知识库列表项 Data，包含编辑弹窗所需的相似问法和答案快照。
 */
class ListKnowledgeQaEntryItemData extends Data
{
    /**
     * @param  list<string>  $similar_questions
     * @param  list<string>  $answers
     */
    public function __construct(
        public string $id,
        public string $knowledge_base_id,
        public string $group_id,
        public string $question,
        public array $similar_questions,
        public array $answers,
        public int $similar_questions_count,
        public int $answers_count,
        public KnowledgeQaEntryStatus $status,
        public string $status_label,
        public ?string $error_message,
        public KnowledgeDocumentIndexingStatus $vector_status,
        public string $vector_status_label,
        public ?string $vector_error,
        public ?string $vector_indexed_at,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    /**
     * 从 Eloquent 模型构造问答列表项。
     */
    public static function fromModel(KnowledgeQaEntry $entry): self
    {
        $entry->loadMissing(['similarQuestions', 'answers']);
        $status = $entry->status;
        $similarQuestions = $entry->similarQuestions
            ->map(fn (KnowledgeQaQuestion $question): string => $question->question)
            ->values()
            ->all();
        $answers = $entry->answers
            ->map(fn (KnowledgeQaAnswer $answer): string => $answer->answer)
            ->values()
            ->all();

        return new self(
            id: (string) $entry->id,
            knowledge_base_id: (string) $entry->knowledge_base_id,
            group_id: (string) $entry->group_id,
            question: $entry->question,
            similar_questions: $similarQuestions,
            answers: $answers,
            similar_questions_count: count($similarQuestions),
            answers_count: count($answers),
            status: $status,
            status_label: $status->label(),
            error_message: filled($entry->error_message) ? (string) $entry->error_message : null,
            vector_status: $entry->vector_status,
            vector_status_label: $entry->vector_status->label(),
            vector_error: filled($entry->vector_error) ? (string) $entry->vector_error : null,
            vector_indexed_at: $entry->vector_indexed_at?->toIso8601String(),
            created_at: $entry->created_at?->toIso8601String(),
            updated_at: $entry->updated_at?->toIso8601String(),
        );
    }
}
