<?php

namespace App\Data\Experience;

use App\Enums\ExperienceExtractionStatus;
use App\Models\ExperienceExtraction;
use Spatie\LaravelData\Data;

/**
 * 经验提炼运行的展示数据，用于 resources/js/pages/experiences/ 下各页的任务信息与知识库上下文。
 */
class ExperienceExtractionData extends Data
{
    public function __construct(
        public string $id,
        public ExperienceKnowledgeBaseData $knowledge_base,
        public ExperienceExtractionStatus $status,
        public string $status_label,
        public ?string $scanned_from,
        public ?string $scanned_until,
        public int $conversation_count,
        public int $candidate_count,
        public ?string $error,
        public string $created_at,
    ) {}

    /**
     * 从模型构造展示数据；存量任务须先由 one-off 回填绑定的问答库，未回填时此处显性报错。
     */
    public static function fromModel(ExperienceExtraction $extraction): self
    {
        return new self(
            id: (string) $extraction->id,
            knowledge_base: ExperienceKnowledgeBaseData::fromModel($extraction->knowledgeBase),
            status: $extraction->status,
            status_label: $extraction->status->label(),
            scanned_from: $extraction->scanned_from?->toIso8601String(),
            scanned_until: $extraction->scanned_until?->toIso8601String(),
            conversation_count: $extraction->conversation_count,
            candidate_count: $extraction->candidate_count,
            error: $extraction->error,
            created_at: $extraction->created_at?->toIso8601String() ?? '',
        );
    }
}
