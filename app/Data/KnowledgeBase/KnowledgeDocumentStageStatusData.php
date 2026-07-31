<?php

namespace App\Data\KnowledgeBase;

use Spatie\LaravelData\Data;

/**
 * 文档单个阶段（parse / vector / raptor）的展示状态。
 * 由 KnowledgeDocumentIndexingDetailData 组装，前端用它渲染单个 badge。
 */
class KnowledgeDocumentStageStatusData extends Data
{
    /**
     * @param  string  $stage  parse / vector / raptor
     */
    public function __construct(
        public string $stage,
        public string $stage_label,
        public string $status,
        public string $status_label,
        public ?string $error_message,
        public ?string $finished_at,
        public bool $enabled,
    ) {}
}
