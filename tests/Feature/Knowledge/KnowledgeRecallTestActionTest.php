<?php

use App\Actions\KnowledgeBase\Indexing\WriteCanonicalChunksAction;
use App\Actions\KnowledgeBase\Qa\CreateKnowledgeQaEntryAction;
use App\Actions\KnowledgeBase\RunKnowledgeRecallTestAction;
use App\Data\KnowledgeBase\FormCreateKnowledgeQaEntryData;
use App\Data\KnowledgeBase\FormKnowledgeSearchData;
use App\Enums\KnowledgeBaseCategory;
use App\Enums\KnowledgeDocumentIndexingStatus;
use App\Enums\KnowledgeDocumentParseStatus;
use App\Enums\KnowledgeSearchMode;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function (): void {
    // canonical 节点写入使用独立的 TNTSearch 索引。
    bootTntSearch();

    $this->user = $this->createUserWithInstance();
    // 召回测试只启用全文索引。
    setKnowledgeEngine(vectorIndexEnabled: false, raptorIndexEnabled: false);
    $this->kb = KnowledgeBase::factory()->create([
        'name' => '产品知识库',
    ]);
});

afterEach(function (): void {
    flushTntSearch();
});

/**
 * 写一篇已解析 + canonical + FTS 的文档，跳过真实解析阶段，专注测召回富集。
 */
function seedRecallDocument(KnowledgeBase $kb, string $body): KnowledgeDocument
{
    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $kb->id,
        'original_filename' => '工单流程说明.md',
        'parse_status' => KnowledgeDocumentParseStatus::Succeeded,
        'parsed_content' => $body,
        'parsed_content_format' => 'markdown',
        'vector_status' => KnowledgeDocumentIndexingStatus::Idle,
    ]);
    app(WriteCanonicalChunksAction::class)->forDocument($document);

    return $document;
}

test('RunKnowledgeRecallTestAction semantic 模式富集文档标题并回填诊断', function (): void {
    $document = seedRecallDocument(
        $this->kb,
        "# 产品手册\n\n工单流程包含创建、流转、关闭三个阶段。",
    );

    /** @var RunKnowledgeRecallTestAction $action */
    $action = app(RunKnowledgeRecallTestAction::class);
    $result = $action->handle($this->kb, FormKnowledgeSearchData::from([
        'mode' => KnowledgeSearchMode::Semantic->value,
        'knowledge_base_ids' => [(string) $this->kb->id],
        'query' => '工单',
    ]));

    expect($result->mode)->toBe('semantic')
        ->and($result->grep_matches)->toBe([])
        ->and($result->semantic_hits)->not->toBeEmpty();

    $first = $result->semantic_hits[0];
    expect($first->origin_type)->toBe('document')
        ->and($first->origin_title)->toBe((string) $document->original_filename)
        ->and($first->source_label)->not->toBe('')
        ->and($result->diagnostics->fulltext)->toBeTrue()
        ->and($result->diagnostics->vector)->toBeFalse()
        ->and($result->diagnostics->semantic_count)->toBe(count($result->semantic_hits));
});

test('RunKnowledgeRecallTestAction grep 模式回填命中来源', function (): void {
    app()->setLocale('zh_CN');
    seedRecallDocument(
        $this->kb,
        "# 产品手册\n\n这里介绍 Helmdesk 的工单流程。",
    );

    /** @var RunKnowledgeRecallTestAction $action */
    $action = app(RunKnowledgeRecallTestAction::class);
    $result = $action->handle($this->kb, FormKnowledgeSearchData::from([
        'mode' => KnowledgeSearchMode::Grep->value,
        'knowledge_base_ids' => [(string) $this->kb->id],
        'query' => 'Helmdesk',
    ]));

    expect($result->mode)->toBe('grep')
        ->and($result->semantic_hits)->toBe([])
        ->and($result->grep_matches)->not->toBeEmpty();

    $first = $result->grep_matches[0];
    expect($first->origin_type)->toBe('document')
        ->and($first->origin_title)->toBe('工单流程说明.md')
        ->and($first->field)->toBe('document.parsed_content')
        ->and($first->match)->toBe('Helmdesk')
        ->and($first->line)->toBeGreaterThan(0);
});

test('RunKnowledgeRecallTestAction 在 QA 知识库上以主问题作为来源标题', function (): void {
    $qaKb = KnowledgeBase::factory()->create([
        'category' => KnowledgeBaseCategory::Qa->value,
        'name' => '产品问答库',
    ]);
    app(CreateKnowledgeQaEntryAction::class)->handle($qaKb, FormCreateKnowledgeQaEntryData::from([
        'question' => '如何申请退款？',
        'similar_questions' => ['退款入口在哪里？'],
        'answers' => ['请在订单详情页提交退款申请。'],
    ]));

    /** @var RunKnowledgeRecallTestAction $action */
    $action = app(RunKnowledgeRecallTestAction::class);
    $result = $action->handle($qaKb, FormKnowledgeSearchData::from([
        'mode' => KnowledgeSearchMode::Semantic->value,
        'knowledge_base_ids' => [(string) $qaKb->id],
        'query' => '申请退款',
    ]));

    expect($result->semantic_hits)->not->toBeEmpty();
    $hit = $result->semantic_hits[0];
    expect($hit->origin_type)->toBe('qa')
        ->and($hit->origin_title)->toBe('如何申请退款？');
});

test('召回测试接口返回结构化 JSON 命中', function (): void {
    seedRecallDocument(
        $this->kb,
        "# 产品手册\n\nHelmdesk 的工单流程。",
    );

    $response = $this->actingAs($this->user)->postJson(
        route('app.manage.knowledge-bases.recall-test', [
            'knowledgeBase' => $this->kb->id,
        ]),
        ['mode' => 'grep', 'query' => 'Helmdesk'],
    );

    $response->assertOk()
        ->assertJsonPath('mode', 'grep')
        ->assertJsonPath('grep_matches.0.match', 'Helmdesk')
        ->assertJsonPath('grep_matches.0.origin_title', '工单流程说明.md');
});

test('召回测试接口对空 query 返回校验错误', function (): void {
    $this->actingAs($this->user)->postJson(
        route('app.manage.knowledge-bases.recall-test', [
            'knowledgeBase' => $this->kb->id,
        ]),
        ['mode' => 'semantic', 'query' => ''],
    )->assertStatus(422)->assertJsonValidationErrorFor('query');
});
