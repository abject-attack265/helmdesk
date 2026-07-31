<?php

namespace App\Data\Experience;

use App\Enums\ExperienceExtractionStatus;
use App\Models\ExperienceExtraction;
use Spatie\LaravelData\Data;

/**
 * 提炼任务列表项，用于 resources/js/pages/experiences/Index.vue 的任务列表。
 */
class ListExperienceExtractionItemData extends Data
{
    public function __construct(
        public string $id,
        public ExperienceExtractionStatus $status,
        public string $status_label,
        public ?string $scanned_from,
        public ?string $scanned_until,
        public int $conversation_count,
        public int $candidate_count,
        public int $pending_candidate_count,
        public ?string $triggered_by_name,
        public ?string $error,
        public string $created_at,
    ) {}

    /**
     * 从模型构造列表项；pending_candidate_count 由查询侧 withCount 提供，triggeredBy 需预加载。
     */
    public static function fromModel(ExperienceExtraction $extraction): self
    {
        return new self(
            id: (string) $extraction->id,
            status: $extraction->status,
            status_label: $extraction->status->label(),
            scanned_from: $extraction->scanned_from?->toIso8601String(),
            scanned_until: $extraction->scanned_until?->toIso8601String(),
            conversation_count: $extraction->conversation_count,
            candidate_count: $extraction->candidate_count,
            pending_candidate_count: (int) ($extraction->pending_candidates_count ?? 0),
            triggered_by_name: $extraction->triggeredBy?->name,
            error: $extraction->error,
            created_at: $extraction->created_at?->toIso8601String() ?? '',
        );
    }
}
