<?php

use App\Enums\KnowledgeIndexingStrategy;
use App\Enums\KnowledgeNodeKind;
use App\Models\KnowledgeNode;
use App\Services\KnowledgeBase\KnowledgeVectorTableManager;
use App\Services\KnowledgeBase\Search\KnowledgeNodeRetriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bootTntSearch();

    $this->knowledgeBaseId = (string) Str::ulid();
    $this->otherKnowledgeBaseId = (string) Str::ulid();
    $this->documentId = (string) Str::ulid();

    $this->orderNode = createTntKnowledgeNode($this->knowledgeBaseId, [
        'document_id' => $this->documentId,
        'content' => '订单查询与物流跟踪',
        'embedding' => [1.0, 0.0, 0.0],
    ]);
    $this->refundNode = createTntKnowledgeNode($this->knowledgeBaseId, [
        'qa_entry_id' => (string) Str::ulid(),
        'qa_question_id' => (string) Str::ulid(),
        'content' => '退货政策与退款流程',
        'embedding' => [0.0, 1.0, 0.0],
    ]);
    $this->summaryNode = createTntKnowledgeNode($this->knowledgeBaseId, [
        'strategy' => KnowledgeIndexingStrategy::Raptor,
        'kind' => KnowledgeNodeKind::Summary,
        'level' => 1,
        'content' => '物流与配送总结',
        'embedding' => [0.0, 0.0, 1.0],
    ]);
    createTntKnowledgeNode($this->otherKnowledgeBaseId, [
        'content' => '订单查询与物流跟踪',
        'embedding' => [1.0, 0.0, 0.0],
    ]);
});

afterEach(function (): void {
    flushTntSearch();
    app(KnowledgeVectorTableManager::class)->resetAllTables();
});

test('向量召回按知识库和策略过滤并返回最近节点', function (): void {
    $hits = app(KnowledgeNodeRetriever::class)->vectorRetrieve(
        [$this->knowledgeBaseId],
        [[0.9, 0.1, 0.0]],
        [KnowledgeIndexingStrategy::Text->value],
        5,
    );

    expect($hits)->not->toBeEmpty();
    $first = $hits[0];

    expect($first->source)->toBe('vector')
        ->and($first->knowledgeNodeId)->toBe($this->orderNode->id)
        ->and($first->content)->toBe('订单查询与物流跟踪')
        ->and($first->knowledgeBaseId)->toBe($this->knowledgeBaseId)
        ->and($first->documentId)->toBe($this->documentId)
        ->and($first->rank)->toBe(1)
        ->and(collect($hits)->pluck('knowledgeNodeId'))->not->toContain($this->summaryNode->id);
});

test('多条查询向量保留各自 query_index', function (): void {
    $hits = app(KnowledgeNodeRetriever::class)->vectorRetrieve(
        [$this->knowledgeBaseId],
        [[0.9, 0.1, 0.0], [0.1, 0.9, 0.0]],
        [KnowledgeIndexingStrategy::Text->value],
        1,
    );

    expect($hits)->toHaveCount(2)
        ->and($hits[0]->knowledgeNodeId)->toBe($this->orderNode->id)
        ->and($hits[0]->metadata['query_index'])->toBe(0)
        ->and($hits[1]->knowledgeNodeId)->toBe($this->refundNode->id)
        ->and($hits[1]->metadata['query_index'])->toBe(1);
});

test('全文召回返回节点来源字段', function (): void {
    $hits = app(KnowledgeNodeRetriever::class)->fullTextRetrieve(
        [$this->knowledgeBaseId],
        ['退货'],
        [KnowledgeIndexingStrategy::Text->value],
        5,
    );

    $hit = collect($hits)->firstWhere('knowledgeNodeId', $this->refundNode->id);

    expect($hit)->not->toBeNull()
        ->and($hit->source)->toBe('fulltext')
        ->and($hit->qaEntryId)->toBe($this->refundNode->qa_entry_id)
        ->and($hit->qaQuestionId)->toBe($this->refundNode->qa_question_id);
});

test('全文召回跳过空白查询并保留原始 query_index', function (): void {
    $hits = app(KnowledgeNodeRetriever::class)->fullTextRetrieve(
        [$this->knowledgeBaseId],
        ['  ', '退货'],
        [KnowledgeIndexingStrategy::Text->value],
        5,
    );

    $hit = collect($hits)->firstWhere('knowledgeNodeId', $this->refundNode->id);

    expect($hit)->not->toBeNull()
        ->and($hit->metadata['query_index'])->toBe(1);
});

test('全文召回先应用知识库范围再截取 top k', function (): void {
    foreach (range(1, 30) as $index) {
        createTntKnowledgeNode($this->otherKnowledgeBaseId, [
            'content' => "范围查询 {$index}",
        ]);
    }

    $target = createTntKnowledgeNode($this->knowledgeBaseId, [
        'content' => '范围查询目标节点',
    ]);

    $hits = app(KnowledgeNodeRetriever::class)->fullTextRetrieve(
        [$this->knowledgeBaseId],
        ['范围查询'],
        [KnowledgeIndexingStrategy::Text->value],
        5,
    );

    expect(collect($hits)->pluck('knowledgeNodeId')->all())->toContain($target->id);
});

test('全文索引只匹配节点正文', function (): void {
    $metadataNode = createTntKnowledgeNode($this->knowledgeBaseId, [
        'content' => '节点正文不包含查询词',
        'heading_path' => 'summary',
        'metadata' => ['label' => 'segment'],
    ]);

    $hits = app(KnowledgeNodeRetriever::class)->fullTextRetrieve(
        [$this->knowledgeBaseId],
        ['summary'],
        [KnowledgeIndexingStrategy::Text->value],
        5,
    );

    expect(collect($hits)->pluck('knowledgeNodeId')->all())->not->toContain($metadataNode->id);
});

function createTntKnowledgeNode(string $knowledgeBaseId, array $attributes = []): KnowledgeNode
{
    $embedding = $attributes['embedding'] ?? null;
    unset($attributes['embedding']);

    $node = KnowledgeNode::query()->create(array_merge([
        'knowledge_base_id' => $knowledgeBaseId,
        'document_id' => null,
        'qa_entry_id' => null,
        'qa_question_id' => null,
        'parent_id' => null,
        'strategy' => KnowledgeIndexingStrategy::Text,
        'level' => 0,
        'kind' => KnowledgeNodeKind::Segment,
        'content' => '',
        'content_format' => 'markdown',
        'heading_path' => null,
        'byte_start' => null,
        'byte_end' => null,
        'token_count' => null,
        'embedding_model_id' => null,
        'embedding_dim' => is_array($embedding) ? count($embedding) : 0,
        'metadata' => null,
    ], $attributes));

    if (is_array($embedding)) {
        app(KnowledgeVectorTableManager::class)->upsertVector(
            count($embedding),
            (string) $node->id,
            array_map(static fn ($value): float => (float) $value, $embedding),
        );
    }

    return $node;
}
