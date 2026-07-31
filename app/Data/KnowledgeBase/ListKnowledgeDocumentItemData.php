<?php

namespace App\Data\KnowledgeBase;

use App\Enums\KnowledgeDocumentSourceType;
use App\Enums\KnowledgeDocumentStatus;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use Spatie\LaravelData\Data;

/** 知识库文档列表项，包含来源、综合处理状态和阶段明细。 */
class ListKnowledgeDocumentItemData extends Data
{
    /**
     * 封装文档列表展示和操作所需的数据。
     */
    public function __construct(
        public string $id,
        public string $knowledge_base_id,
        public string $group_id,
        public string $original_filename,
        public string $mime_type,
        public int $byte_size,
        public ?string $extension,
        public KnowledgeDocumentSourceType $source_type,
        public KnowledgeDocumentStatus $status,
        public string $status_label,
        public ?string $error_message,
        public KnowledgeDocumentIndexingDetailData $indexing,
        public ?string $created_at,
        public ?string $updated_at,
    ) {}

    /**
     * 从模型构造列表项。
     *
     * 列表使用文档持久化的综合状态，阶段明细由 indexing 单独提供。
     */
    public static function fromModel(KnowledgeDocument $document, KnowledgeBase $knowledgeBase): self
    {
        $strategies = $knowledgeBase->enabledIndexingStrategies();

        return new self(
            id: (string) $document->id,
            knowledge_base_id: (string) $document->knowledge_base_id,
            group_id: (string) $document->group_id,
            original_filename: $document->original_filename,
            mime_type: $document->mime_type,
            byte_size: (int) $document->byte_size,
            extension: filled($document->extension) ? (string) $document->extension : null,
            source_type: $document->source_type,
            status: $document->status,
            status_label: $document->status->label(),
            error_message: filled($document->error_message) ? (string) $document->error_message : null,
            indexing: KnowledgeDocumentIndexingDetailData::fromModels($knowledgeBase, $document, $strategies),
            created_at: $document->created_at?->toIso8601String(),
            updated_at: $document->updated_at?->toIso8601String(),
        );
    }

    /**
     * 批量构造列表项，共享同一份已启用策略解析结果。
     *
     * @param  iterable<KnowledgeDocument>  $documents
     * @return list<self>
     */
    public static function fromCollection(iterable $documents, KnowledgeBase $knowledgeBase): array
    {
        $items = [];
        foreach ($documents as $document) {
            $items[] = self::fromModel($document, $knowledgeBase);
        }

        return $items;
    }
}
