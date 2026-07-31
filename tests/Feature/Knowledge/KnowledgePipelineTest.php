<?php

use App\Actions\KnowledgeBase\Indexing\DispatchKnowledgeDocumentPipelineAction;
use App\Actions\KnowledgeBase\Indexing\IndexKnowledgeDocumentRaptorAction;
use App\Actions\KnowledgeBase\Indexing\IndexKnowledgeDocumentVectorAction;
use App\Actions\KnowledgeBase\Indexing\ParseKnowledgeDocumentAction;
use App\Actions\KnowledgeBase\Indexing\WriteCanonicalChunksAction;
use App\Enums\AiModelPurpose;
use App\Enums\KnowledgeChunkingStrategy;
use App\Enums\KnowledgeDocumentIndexingStatus;
use App\Enums\KnowledgeDocumentParseStatus;
use App\Enums\KnowledgeDocumentSourceType;
use App\Enums\KnowledgeDocumentStatus;
use App\Enums\KnowledgeIndexingStrategy;
use App\Enums\KnowledgeNodeKind;
use App\Jobs\KnowledgeDocument\IndexRaptorKnowledgeDocumentJob;
use App\Jobs\KnowledgeDocument\IndexVectorKnowledgeDocumentJob;
use App\Jobs\KnowledgeDocument\ParseKnowledgeDocumentJob;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeNode;
use App\Services\KnowledgeBase\KnowledgeEmbeddingService;
use App\Services\KnowledgeBase\KnowledgeSummarizer;
use App\Services\KnowledgeBase\Parsing\DocumentParserManager;
use App\Services\KnowledgeBase\Parsing\ParsedDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\WithInstance;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    // canonical 节点和向量写入使用独立的 TNTSearch 索引。
    bootTntSearch();

    $this->user = $this->createUserWithInstance();

    // 测试配置提供可用的嵌入模型、摘要模型和向量索引。
    $provider = makeUsableAiProvider();
    $this->embeddingModel = pinKnowledgeEmbeddingModel();
    $this->summaryModel = makeAiModel(AiModelPurpose::BackgroundTask, $provider);

    setKnowledgeEngine(
        vectorIndexEnabled: true,
        raptorIndexEnabled: false,
        chunkingStrategy: KnowledgeChunkingStrategy::Fixed,
        chunkMaxTokens: 256,
        chunkOverlapTokens: 32,
    );
    $this->kb = KnowledgeBase::factory()->create([
    ]);
});

afterEach(function () {
    flushTntSearch();
});

test('编排器为启用的索引策略写入 Pending，并按策略派发 Job', function () {
    Bus::fake();

    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'parse_status' => KnowledgeDocumentParseStatus::Pending,
    ]);

    app(DispatchKnowledgeDocumentPipelineAction::class)->handle($document);

    $document->refresh();
    expect($document->parse_status)->toBe(KnowledgeDocumentParseStatus::Pending)
        ->and($document->vector_status)->toBe(KnowledgeDocumentIndexingStatus::Pending)
        ->and($document->raptor_status)->toBe(KnowledgeDocumentIndexingStatus::Idle);

    Bus::assertDispatched(ParseKnowledgeDocumentJob::class);
    Bus::assertNotDispatched(IndexVectorKnowledgeDocumentJob::class);
    Bus::assertNotDispatched(IndexRaptorKnowledgeDocumentJob::class);
});

test('解析成功后会派发已启用策略对应的索引 Job', function () {
    Bus::fake();

    setKnowledgeEngine(vectorIndexEnabled: true, raptorIndexEnabled: true);

    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'parse_status' => KnowledgeDocumentParseStatus::Succeeded,
        'parsed_content' => "# 标题\n\n正文段落",
        'parsed_content_format' => 'markdown',
    ]);

    app(DispatchKnowledgeDocumentPipelineAction::class)
        ->dispatchIndexingForParsedDocument($document);

    Bus::assertDispatched(IndexVectorKnowledgeDocumentJob::class);
    Bus::assertDispatched(IndexRaptorKnowledgeDocumentJob::class);
});

test('ParseAction 调用 DocumentParserManager 成功后写入 parsed_content 并把策略转 Pending', function () {
    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'source_type' => KnowledgeDocumentSourceType::Manual,
        'parse_status' => KnowledgeDocumentParseStatus::Pending,
        'content' => "# 测试\n\n这是手动写的文档内容，长度足够。",
    ]);

    mock(DocumentParserManager::class, function ($mock): void {
        $mock->shouldReceive('parse')->once()->andReturn(new ParsedDocument(
            markdown: "# 测试\n\n这是手动写的文档内容，长度足够。",
            contentFormat: 'markdown',
            metadata: ['parser' => 'text', 'source' => 'unit-test'],
        ));
    });

    app(ParseKnowledgeDocumentAction::class)->handle($document);

    $document->refresh();
    expect($document->parse_status)->toBe(KnowledgeDocumentParseStatus::Succeeded)
        ->and($document->parsed_content_format)->toBe('markdown')
        ->and($document->parsed_content)->toContain('测试')
        ->and($document->parse_metadata['outline'][0]['heading'] ?? null)->toBe('测试')
        ->and($document->vector_status)->toBe(KnowledgeDocumentIndexingStatus::Pending)
        ->and($document->raptor_status)->toBe(KnowledgeDocumentIndexingStatus::Idle)
        ->and($document->status)->toBe(KnowledgeDocumentStatus::Indexing);
});

test('ParseAction 失败时把 parse_status 置 Failed 并向上抛出', function () {
    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'source_type' => KnowledgeDocumentSourceType::Manual,
        'parse_status' => KnowledgeDocumentParseStatus::Pending,
        'content' => '一些手动内容',
    ]);

    mock(DocumentParserManager::class, function ($mock): void {
        $mock->shouldReceive('parse')->once()->andThrow(new RuntimeException('parser exploded'));
    });

    expect(fn () => app(ParseKnowledgeDocumentAction::class)->handle($document))
        ->toThrow(RuntimeException::class, 'parser exploded');

    $document->refresh();
    expect($document->parse_status)->toBe(KnowledgeDocumentParseStatus::Failed)
        ->and($document->parse_error)->toContain('parser exploded')
        ->and($document->status)->toBe(KnowledgeDocumentStatus::Failed);
});

test('上传文档缺少原始附件时解析失败', function () {
    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'source_type' => KnowledgeDocumentSourceType::Upload,
        'parse_status' => KnowledgeDocumentParseStatus::Pending,
        'content' => '# 数据库副本不能替代原始附件',
    ]);

    expect(fn () => app(ParseKnowledgeDocumentAction::class)->handle($document))
        ->toThrow(RuntimeException::class);

    $document->refresh();
    expect($document->parse_status)->toBe(KnowledgeDocumentParseStatus::Failed)
        ->and($document->status)->toBe(KnowledgeDocumentStatus::Failed);
});

test('WriteCanonicalChunksAction 写 canonical 文本节点', function () {
    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'parse_status' => KnowledgeDocumentParseStatus::Succeeded,
        'parsed_content' => "# 标题\n\n正文段落 1\n\n正文段落 2",
        'parsed_content_format' => 'markdown',
        'parse_metadata' => ['outline' => [['heading' => '标题', 'level' => 1]]],
    ]);

    $nodes = app(WriteCanonicalChunksAction::class)->forDocument($document);

    $canonical = KnowledgeNode::query()
        ->where('document_id', (string) $document->id)
        ->where('strategy', KnowledgeIndexingStrategy::Text)
        ->where('kind', KnowledgeNodeKind::Segment)
        ->get();

    expect($nodes->count())->toBeGreaterThanOrEqual(1)
        ->and($canonical->count())->toBe($nodes->count());
});

test('VectorAction 把 canonical 节点附加向量并把 vector_status 置 Succeeded', function () {
    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'parse_status' => KnowledgeDocumentParseStatus::Succeeded,
        'parsed_content' => "# 标题\n\n第一段\n\n第二段",
        'parsed_content_format' => 'markdown',
        'vector_status' => KnowledgeDocumentIndexingStatus::Pending,
    ]);

    $this->partialMock(KnowledgeEmbeddingService::class, function ($mock): void {
        $mock->shouldReceive('embedTexts')->andReturnUsing(static fn ($_model, array $contents): array => [3, array_map(static fn () => [0.1, 0.2, 0.3], $contents)]);
    });

    app(WriteCanonicalChunksAction::class)->forDocument($document);
    app(IndexKnowledgeDocumentVectorAction::class)->handle($document);

    $document->refresh();
    expect($document->vector_status)->toBe(KnowledgeDocumentIndexingStatus::Succeeded);

    $nodes = KnowledgeNode::query()
        ->where('document_id', (string) $document->id)
        ->where('strategy', KnowledgeIndexingStrategy::Text)
        ->where('kind', KnowledgeNodeKind::Segment)
        ->get();

    expect($nodes->count())->toBeGreaterThanOrEqual(1)
        ->and($nodes->every(fn ($node) => (int) $node->embedding_dim === 3))->toBeTrue();
});

test('VectorAction 使用句子 embedding 聚合语义分段', function () {
    setKnowledgeEngine(
        chunkingStrategy: KnowledgeChunkingStrategy::Semantic,
        chunkMaxTokens: 256,
        chunkOverlapTokens: 0,
    );

    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'parse_status' => KnowledgeDocumentParseStatus::Succeeded,
        'parsed_content' => "# 标题\n\n猫喜欢鱼。猫会抓老鼠。数据库支持事务。索引提升查询速度。",
        'parsed_content_format' => 'markdown',
        'vector_status' => KnowledgeDocumentIndexingStatus::Pending,
    ]);

    $this->partialMock(KnowledgeEmbeddingService::class, function ($mock): void {
        $mock->shouldReceive('embedTexts')->andReturnUsing(static function ($_model, array $contents): array {
            $embeddings = count($contents) === 4
                ? [[1.0, 0.0], [0.98, 0.02], [0.0, 1.0], [0.02, 0.98]]
                : array_map(static fn () => [0.5, 0.5], $contents);

            return [2, $embeddings];
        });
    });

    app(WriteCanonicalChunksAction::class)->forDocument($document);
    app(IndexKnowledgeDocumentVectorAction::class)->handle($document);

    $contents = KnowledgeNode::query()
        ->where('document_id', (string) $document->id)
        ->where('strategy', KnowledgeIndexingStrategy::Text)
        ->where('kind', KnowledgeNodeKind::Segment)
        ->orderByRaw('COALESCE(byte_start, 0) ASC')
        ->orderBy('id')
        ->pluck('content')
        ->all();

    expect($contents)->toHaveCount(2)
        ->and($contents[0])->toContain('猫喜欢鱼')
        ->and($contents[0])->toContain('猫会抓老鼠')
        ->and($contents[1])->toContain('数据库支持事务')
        ->and($contents[1])->toContain('索引提升查询速度');
});

test('RaptorAction 使用摘要模型生成摘要树并把 raptor_status 置 Succeeded', function () {
    setKnowledgeEngine(
        vectorIndexEnabled: false,
        raptorIndexEnabled: true,
        // 较小的固定分块上限让测试内容形成多个叶子段。
        chunkingStrategy: KnowledgeChunkingStrategy::Fixed,
        chunkMaxTokens: 16,
        chunkOverlapTokens: 0,
    );
    $knowledgeBase = KnowledgeBase::factory()->create([
    ]);
    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $knowledgeBase->id,
        'parse_status' => KnowledgeDocumentParseStatus::Succeeded,
        'parsed_content' => "# 标题\n\n第一段内容比较长一些方便切段\n\n第二段内容比较长一些方便切段\n\n第三段内容比较长一些方便切段\n\n第四段内容比较长一些方便切段",
        'parsed_content_format' => 'markdown',
        'raptor_status' => KnowledgeDocumentIndexingStatus::Pending,
    ]);

    $this->partialMock(KnowledgeEmbeddingService::class, function ($mock): void {
        $mock->shouldReceive('embedTexts')->andReturnUsing(static fn ($_model, array $contents): array => [3, array_map(static fn () => [0.1, 0.2, 0.3], $contents)]);
    });
    mock(KnowledgeSummarizer::class, function ($mock): void {
        $mock->shouldReceive('summarizeBatches')->andReturnUsing(static fn ($_model, array $batches): array => array_map(
            static fn (array $contents, int $index): string => '摘要 '.($index + 1).': '.implode(' ', $contents),
            $batches,
            array_keys($batches),
        ));
    });

    app(WriteCanonicalChunksAction::class)->forDocument($document);
    app(IndexKnowledgeDocumentRaptorAction::class)->handle($document);

    $document->refresh();
    $leafNodes = KnowledgeNode::query()
        ->where('document_id', (string) $document->id)
        ->where('strategy', KnowledgeIndexingStrategy::Text)
        ->where('kind', KnowledgeNodeKind::Segment)
        ->get();
    $summaryNodes = KnowledgeNode::query()
        ->where('document_id', (string) $document->id)
        ->where('strategy', KnowledgeIndexingStrategy::Raptor)
        ->where('kind', KnowledgeNodeKind::Summary)
        ->get();

    $leafIds = $leafNodes->pluck('id')->map(static fn ($id) => (string) $id)->all();
    $coveredLeafIds = collect();
    foreach ($summaryNodes as $summary) {
        $children = $summary->metadata['children_ids'] ?? [];
        if (is_array($children)) {
            $coveredLeafIds = $coveredLeafIds->merge($children);
        }
    }

    expect($document->raptor_status)->toBe(KnowledgeDocumentIndexingStatus::Succeeded)
        ->and($leafNodes->count())->toBeGreaterThanOrEqual(1)
        ->and($summaryNodes->count())->toBeGreaterThanOrEqual(1)
        // RAPTOR 不给 canonical 叶子写入向量。
        ->and($leafNodes->every(fn ($node) => (int) $node->embedding_dim === 0))->toBeTrue()
        // RAPTOR 摘要节点包含召回所需的向量。
        ->and($summaryNodes->every(fn ($node) => (int) $node->embedding_dim === 3))->toBeTrue()
        // canonical 叶子不加入摘要树的 parent_id 链。
        ->and($leafNodes->every(fn ($node) => $node->parent_id === null))->toBeTrue()
        // 每个 canonical 叶子都由一层摘要节点覆盖。
        ->and(
            collect($leafIds)
                ->every(fn (string $id) => $coveredLeafIds->contains($id))
        )->toBeTrue();
});
