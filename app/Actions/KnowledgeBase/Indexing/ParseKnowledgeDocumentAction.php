<?php

namespace App\Actions\KnowledgeBase\Indexing;

use App\Enums\KnowledgeDocumentIndexingStatus;
use App\Enums\KnowledgeDocumentParseStatus;
use App\Enums\KnowledgeIndexingStrategy;
use App\Models\KnowledgeDocument;
use App\Services\KnowledgeBase\KnowledgeDocumentSourceMaterializer;
use App\Services\KnowledgeBase\Parsing\DocumentParserManager;
use App\Services\KnowledgeBase\Parsing\MarkdownChunker;
use Illuminate\Support\Facades\Log;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * 文档解析阶段，读取原始文件并写入归一化 Markdown、大纲和索引初始状态。
 */
class ParseKnowledgeDocumentAction
{
    use AsAction;

    /**
     * 注入解析、分块、源文件物化和 canonical 节点写入服务。
     */
    public function __construct(
        private readonly DocumentParserManager $parsers,
        private readonly MarkdownChunker $chunker,
        private readonly KnowledgeDocumentSourceMaterializer $materializer,
        private readonly WriteCanonicalChunksAction $canonicalWriter,
    ) {}

    /**
     * 执行一次解析并写入全文索引。
     */
    public function handle(KnowledgeDocument $document): void
    {
        $document->refresh();
        $knowledgeBase = $document->knowledgeBase;
        if ($knowledgeBase === null) {
            throw new LogicException(sprintf(
                'Knowledge document [%s] has no knowledge base.',
                $document->id,
            ));
        }

        $document->update([
            'parse_status' => KnowledgeDocumentParseStatus::Processing,
            'parse_error' => null,
        ]);

        $tempPath = null;
        $startedAt = microtime(true);
        try {
            $source = $this->materializer->materialize($document);
            $tempPath = $source['path'];

            $parsed = $this->parsers->parse(
                absoluteFilePath: $source['path'],
                mimeType: $source['mime_type'],
                extension: $source['extension'],
            );

            $markdown = trim($parsed->markdown);
            if ($markdown === '') {
                throw new \RuntimeException(__('knowledge_base.documents.errors.no_segments'));
            }

            $outline = $this->chunker->outline($markdown);

            $metadata = $parsed->metadata;
            $metadata['outline'] = $outline;
            $metadata['outline_count'] = count($outline);

            $document->update([
                'parse_status' => KnowledgeDocumentParseStatus::Succeeded,
                'parse_error' => null,
                'parsed_at' => now(),
                'parsed_content_format' => $parsed->contentFormat,
                'parsed_content' => $markdown,
                'parse_metadata' => $metadata,
                'vector_status' => $knowledgeBase->hasIndexingStrategy(KnowledgeIndexingStrategy::Vector)
                    ? KnowledgeDocumentIndexingStatus::Pending
                    : KnowledgeDocumentIndexingStatus::Idle,
                'raptor_status' => $knowledgeBase->hasIndexingStrategy(KnowledgeIndexingStrategy::Raptor)
                    ? KnowledgeDocumentIndexingStatus::Pending
                    : KnowledgeDocumentIndexingStatus::Idle,
            ]);

            $this->canonicalWriter->forDocument($document);
            $document->refreshOverallStatus($knowledgeBase);

            Log::info('[knowledge] 文档解析完成', [
                'knowledge_base_id' => (string) $knowledgeBase->id,
                'document_id' => (string) $document->id,
                'content_length' => strlen($markdown),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

        } catch (Throwable $exception) {
            Log::warning('[knowledge] 文档解析失败', [
                'knowledge_base_id' => (string) $knowledgeBase->id,
                'document_id' => $document->id,
                'message' => $exception->getMessage(),
            ]);

            $document->update([
                'parse_status' => KnowledgeDocumentParseStatus::Failed,
                'parse_error' => $exception->getMessage(),
            ]);
            $document->refreshOverallStatus($knowledgeBase);

            throw $exception;
        } finally {
            if ($tempPath !== null) {
                $this->materializer->cleanup($tempPath);
            }
        }
    }
}
