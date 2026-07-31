<?php

use App\Enums\KnowledgeDocumentParseStatus;
use App\Enums\KnowledgeDocumentStatus;
use App\Models\Attachment;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeNode;
use App\Services\KnowledgeBase\KnowledgeDocumentPipelineLock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function (): void {
    $this->withoutVite();
    bootTntSearch();
    fakeAttachmentStorage();
    setKnowledgeEngine(vectorIndexEnabled: false, raptorIndexEnabled: false);

    $this->user = $this->createUserWithInstance();
    $this->knowledgeBase = KnowledgeBase::factory()->create([
        'name' => '文档生命周期知识库',
    ]);
});

afterEach(function (): void {
    flushTntSearch();
});

test('上传文档经过解析分块和全文索引后可召回，删除后同步清理全部数据', function (): void {
    $file = UploadedFile::fake()->createWithContent(
        'refund-policy.md',
        "# 退款政策\n\n订单支付后七天内可以申请原路退款。",
    );

    $this->actingAs($this->user)
        ->post(route('app.manage.knowledge-bases.documents.store', [
            'knowledgeBase' => $this->knowledgeBase->id,
        ]), ['files' => [$file]])
        ->assertRedirect();

    $document = KnowledgeDocument::query()
        ->where('knowledge_base_id', $this->knowledgeBase->id)
        ->firstOrFail();
    $attachment = $document->originalFile()->firstOrFail();

    expect($document->parse_status)->toBe(KnowledgeDocumentParseStatus::Succeeded)
        ->and($document->status)->toBe(KnowledgeDocumentStatus::Indexed)
        ->and($document->parsed_content)->toContain('七天内可以申请原路退款')
        ->and($document->parse_metadata['outline'][0]['heading'] ?? null)->toBe('退款政策')
        ->and(KnowledgeNode::query()->where('document_id', $document->id)->count())->toBeGreaterThan(0)
        ->and(glob(storage_path('app/private/knowledge-temp/'.$document->id.'-*')) ?: [])->toBe([]);

    $this->actingAs($this->user)
        ->postJson(route('app.manage.knowledge-bases.recall-test', [
            'knowledgeBase' => $this->knowledgeBase->id,
        ]), [
            'mode' => 'semantic',
            'query' => '七天退款',
        ])
        ->assertOk()
        ->assertJsonPath('semantic_hits.0.origin_title', 'refund-policy.md');

    $this->actingAs($this->user)
        ->delete(route('app.manage.knowledge-bases.documents.destroy', [
            'knowledgeBase' => $this->knowledgeBase->id,
            'document' => $document->id,
        ]))
        ->assertRedirect();

    expect(KnowledgeDocument::query()->whereKey($document->id)->exists())->toBeFalse()
        ->and(Attachment::query()->whereKey($attachment->id)->exists())->toBeFalse()
        ->and(KnowledgeNode::query()->where('document_id', $document->id)->exists())->toBeFalse();

    $attachment->filesystem()->assertMissing($attachment->object_key);

    $this->actingAs($this->user)
        ->postJson(route('app.manage.knowledge-bases.recall-test', [
            'knowledgeBase' => $this->knowledgeBase->id,
        ]), [
            'mode' => 'semantic',
            'query' => '七天退款',
        ])
        ->assertOk()
        ->assertJsonCount(0, 'semantic_hits');
});

test('删除独占锁不能越过正在运行的索引阶段', function (): void {
    $pipelineLock = app(KnowledgeDocumentPipelineLock::class);
    $exclusiveRan = false;

    $pipelineLock->runVectorIndexing('document-lock-contract', 0, function () use ($pipelineLock, &$exclusiveRan): void {
        expect(fn () => $pipelineLock->runExclusively(
            'document-lock-contract',
            0,
            function () use (&$exclusiveRan): void {
                $exclusiveRan = true;
            },
        ))->toThrow(LockTimeoutException::class);
    });

    expect($exclusiveRan)->toBeFalse();
});
