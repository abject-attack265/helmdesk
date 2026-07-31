<?php

use App\Actions\KnowledgeBase\Document\DeleteKnowledgeDocumentAction;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Services\KnowledgeBase\KnowledgeNodeRepository;
use App\Services\KnowledgeBase\Search\KnowledgeNodeRetriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    createSystemSettings();
    bootTntSearch();
});

afterEach(function (): void {
    flushTntSearch();
});

test('删除知识库时同步清理全文索引节点', function (): void {
    $knowledgeBase = KnowledgeBase::factory()->create();
    $document = KnowledgeDocument::factory()->create(['knowledge_base_id' => $knowledgeBase->id]);
    $repository = app(KnowledgeNodeRepository::class);

    $repository->writeCanonicalSegments($knowledgeBase, $document, [
        ['content' => '退款政策说明文档', 'content_format' => 'markdown'],
        ['content' => '物流配送时效说明', 'content_format' => 'markdown'],
    ]);

    $retriever = app(KnowledgeNodeRetriever::class);
    expect($retriever->fullTextRetrieve([(string) $knowledgeBase->id], ['退款'], ['text'], 5))->not->toBeEmpty();

    $repository->purgeAllForKnowledgeBase($knowledgeBase);

    expect($retriever->fullTextRetrieve([(string) $knowledgeBase->id], ['退款', '物流'], ['text'], 5))->toBe([]);
});

test('删除单个文档只清理该文档的全文索引节点', function (): void {
    $knowledgeBase = KnowledgeBase::factory()->create();
    $documentA = KnowledgeDocument::factory()->create(['knowledge_base_id' => $knowledgeBase->id]);
    $documentB = KnowledgeDocument::factory()->create(['knowledge_base_id' => $knowledgeBase->id]);
    $repository = app(KnowledgeNodeRepository::class);

    $repository->writeCanonicalSegments($knowledgeBase, $documentA, [
        ['content' => '文档A的内容退款', 'content_format' => 'markdown'],
    ]);
    $repository->writeCanonicalSegments($knowledgeBase, $documentB, [
        ['content' => '文档B的内容物流', 'content_format' => 'markdown'],
    ]);

    $repository->purgeAllForDocument($documentA);

    $retriever = app(KnowledgeNodeRetriever::class);
    expect($retriever->fullTextRetrieve([(string) $knowledgeBase->id], ['退款'], ['text'], 5))->toBe([])
        ->and($retriever->fullTextRetrieve([(string) $knowledgeBase->id], ['物流'], ['text'], 5))->not->toBeEmpty();
});

test('删除文档节点时同步清理对应的向量表记录', function (): void {
    $knowledgeBase = KnowledgeBase::factory()->create();
    $document = KnowledgeDocument::factory()->create(['knowledge_base_id' => $knowledgeBase->id]);
    $repository = app(KnowledgeNodeRepository::class);

    $nodeIds = $repository->writeCanonicalSegments($knowledgeBase, $document, [
        ['content' => '退款政策向量内容', 'content_format' => 'markdown'],
    ]);
    $nodeId = array_first($nodeIds);

    $repository->attachVectors(3, [
        $nodeId => [0.1, 0.2, 0.3],
    ]);

    expect(DB::connection('sqlite_rag')
        ->table('knowledge_node_vectors_3')
        ->where('node_id', $nodeId)
        ->exists())->toBeTrue();

    app(DeleteKnowledgeDocumentAction::class)->handle($document);

    expect(DB::connection('sqlite_rag')
        ->table('knowledge_node_vectors_3')
        ->where('node_id', $nodeId)
        ->exists())->toBeFalse()
        ->and(KnowledgeDocument::query()->whereKey($document->id)->exists())->toBeFalse();
});
