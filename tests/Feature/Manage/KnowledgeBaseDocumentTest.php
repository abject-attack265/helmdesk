<?php

use App\Actions\KnowledgeBase\Document\UpdateManualKnowledgeDocumentAction;
use App\Data\KnowledgeBase\FormUpdateManualKnowledgeDocumentData;
use App\Enums\KnowledgeBaseCategory;
use App\Enums\KnowledgeDocumentSourceType;
use App\Enums\KnowledgeDocumentStatus;
use App\Enums\KnowledgeIndexingStrategy;
use App\Enums\KnowledgeNodeKind;
use App\Exceptions\BusinessException;
use App\Jobs\KnowledgeDocument\IndexRaptorKnowledgeDocumentJob;
use App\Jobs\KnowledgeDocument\IndexVectorKnowledgeDocumentJob;
use App\Jobs\KnowledgeDocument\ParseKnowledgeDocumentJob;
use App\Models\Attachment;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeGroup;
use App\Models\KnowledgeNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->withoutVite();

    Bus::fake([
        ParseKnowledgeDocumentJob::class,
        IndexVectorKnowledgeDocumentJob::class,
        IndexRaptorKnowledgeDocumentJob::class,
    ]);

    $this->user = $this->createUserWithInstance();
    $this->kb = KnowledgeBase::factory()->create([
        'name' => '产品知识库',
    ]);
});

test('所有者可以将 .md 文件上传到知识库默认分组下', function () {
    fakeAttachmentStorage();

    $content = "# 退款政策\n\n本文档说明退款流程，仅供测试。";
    $file = UploadedFile::fake()->createWithContent('refund-policy.md', $content);
    $defaultGroup = $this->kb->defaultDocumentGroup()->firstOrFail();

    $this->actingAs($this->user)
        ->post(
            route('app.manage.knowledge-bases.documents.store', [
                'knowledgeBase' => $this->kb->id,
            ]),
            ['files' => [$file]]
        )
        ->assertRedirect();

    $document = KnowledgeDocument::query()
        ->where('knowledge_base_id', $this->kb->id)
        ->firstOrFail();

    expect($document->group_id)->toBe((string) $defaultGroup->id)
        ->and($document->original_filename)->toBe('refund-policy.md')
        ->and($document->extension)->toBe('md')
        ->and($document->status)->toBe(KnowledgeDocumentStatus::Pending)
        ->and($document->error_message)->toBeNull()
        ->and($document->source_type)->toBe(KnowledgeDocumentSourceType::Upload)
        ->and($document->byte_size)->toBe(strlen($content))
        ->and($document->checksum_sha256)->toBe(hash('sha256', $content))
        ->and($document->content)->toBe($content)
        ->and($document->uploaded_by_user_id)->toBe((string) $this->user->id);

    $attachment = $document->originalFile()->firstOrFail();
    expect($attachment->original_name)->toBe('refund-policy.md')
        ->and($attachment->purpose->value)->toBe('knowledge_document')
        ->and($attachment->attachable_id)->toBe($document->id)
        ->and($attachment->checksum_sha256)->toBe(hash('sha256', $content));

    $attachment->filesystem()->assertExists($attachment->object_key);
});

test('axios JSON 上传返回创建的文档列表数据', function () {
    fakeAttachmentStorage();

    $file = UploadedFile::fake()->createWithContent('intro.md', "# 简介\n");

    $response = $this->actingAs($this->user)
        ->postJson(
            route('app.manage.knowledge-bases.documents.store', [
                'knowledgeBase' => $this->kb->id,
            ]),
            ['files' => [$file]]
        );

    $response->assertSuccessful()
        ->assertJsonPath('documents.0.original_filename', 'intro.md')
        ->assertJsonPath('documents.0.status', KnowledgeDocumentStatus::Pending->value)
        ->assertJsonPath('documents.0.status_label', __('knowledge_base.documents.statuses.pending'));
});

test('单次提交可以批量上传多个不同格式的文档', function () {
    fakeAttachmentStorage();

    $first = UploadedFile::fake()->createWithContent('first.md', "# 第一份\n");
    $second = UploadedFile::fake()->createWithContent('second.txt', "纯文本第二份\n");
    $third = UploadedFile::fake()->create('third.pdf', 16, 'application/pdf');

    $this->actingAs($this->user)
        ->post(
            route('app.manage.knowledge-bases.documents.store', [
                'knowledgeBase' => $this->kb->id,
            ]),
            ['files' => [$first, $second, $third]]
        )
        ->assertRedirect();

    expect(KnowledgeDocument::query()->where('knowledge_base_id', $this->kb->id)->count())
        ->toBe(3)
        ->and(KnowledgeDocument::query()->pluck('extension')->all())
        ->toEqualCanonicalizing(['md', 'txt', 'pdf']);
});

test('问答知识库不能上传普通文档', function () {
    fakeAttachmentStorage();

    $qaKnowledgeBase = KnowledgeBase::factory()->create([
        'category' => KnowledgeBaseCategory::Qa->value,
    ]);
    $file = UploadedFile::fake()->createWithContent('intro.md', '# 简介');

    $this->actingAs($this->user)
        ->from(route('app.manage.knowledge-bases.index', []))
        ->withHeader('X-Inertia', 'true')
        ->post(
            route('app.manage.knowledge-bases.documents.store', [
                'knowledgeBase' => $qaKnowledgeBase->id,
            ]),
            ['files' => [$file]]
        )
        ->assertSessionHasErrors('toast');

    expect(KnowledgeDocument::query()->where('knowledge_base_id', $qaKnowledgeBase->id)->count())->toBe(0);
});

test('二进制格式的文档保留原文件但不直接抽取正文', function () {
    fakeAttachmentStorage();

    $file = UploadedFile::fake()->create('manual.pdf', 32, 'application/pdf');

    $this->actingAs($this->user)
        ->post(
            route('app.manage.knowledge-bases.documents.store', [
                'knowledgeBase' => $this->kb->id,
            ]),
            ['files' => [$file]]
        )
        ->assertRedirect();

    $document = KnowledgeDocument::query()->firstOrFail();
    expect($document->extension)->toBe('pdf')
        ->and($document->content)->toBeNull()
        ->and($document->checksum_sha256)->not->toBeNull()
        ->and($document->originalFile)->not->toBeNull();

    $document->originalFile->filesystem()->assertExists($document->originalFile->object_key);
});

test('可以以内联方式读取知识库文档原文件', function () {
    fakeAttachmentStorage();

    $content = "%PDF-1.4\npreview";
    $file = UploadedFile::fake()->createWithContent('manual.pdf', $content);

    $this->actingAs($this->user)
        ->post(
            route('app.manage.knowledge-bases.documents.store', [
                'knowledgeBase' => $this->kb->id,
            ]),
            ['files' => [$file]]
        )
        ->assertRedirect();

    $document = KnowledgeDocument::query()->firstOrFail();

    $response = $this->actingAs($this->user)
        ->get(
            route('app.manage.knowledge-bases.documents.preview-file', [
                'knowledgeBase' => $this->kb->id,
                'document' => $document->id,
            ])
        );

    $response->assertSuccessful();
    expect($response->headers->get('content-disposition'))->toStartWith('inline;')
        ->and($response->streamedContent())->toBe($content);
});

test('HTML 文档原文件以 text/plain 内联输出，避免在应用 origin 下执行脚本', function () {
    fakeAttachmentStorage();

    $content = '<script>alert(document.cookie)</script><h1>hi</h1>';
    $file = UploadedFile::fake()->createWithContent('evil.html', $content);

    $this->actingAs($this->user)
        ->post(
            route('app.manage.knowledge-bases.documents.store', [
                'knowledgeBase' => $this->kb->id,
            ]),
            ['files' => [$file]]
        )
        ->assertRedirect();

    $document = KnowledgeDocument::query()->firstOrFail();

    $response = $this->actingAs($this->user)
        ->get(
            route('app.manage.knowledge-bases.documents.preview-file', [
                'knowledgeBase' => $this->kb->id,
                'document' => $document->id,
            ])
        );

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toBe('text/plain; charset=utf-8')
        ->and($response->headers->get('x-content-type-options'))->toBe('nosniff')
        ->and($response->streamedContent())->toBe($content);
});

test('批量上传中包含不支持扩展名的文件时校验失败', function () {
    $valid = UploadedFile::fake()->createWithContent('ok.md', '# OK');
    $invalid = UploadedFile::fake()->create('bad.exe', 4);

    $this->actingAs($this->user)
        ->from(route('app.manage.knowledge-bases.index', []))
        ->post(
            route('app.manage.knowledge-bases.documents.store', [
                'knowledgeBase' => $this->kb->id,
            ]),
            ['files' => [$valid, $invalid]]
        )
        ->assertSessionHasErrors();

    expect(KnowledgeDocument::query()->count())->toBe(0);
});

test('指定属于当前知识库的分组时文档归到该分组', function () {
    fakeAttachmentStorage();

    $group = KnowledgeGroup::query()->create([
        'knowledge_base_id' => $this->kb->id,
        'name' => '使用手册',
        'sort_order' => 0,
    ]);

    $file = UploadedFile::fake()->createWithContent('intro.md', '# 简介');

    $this->actingAs($this->user)
        ->post(
            route('app.manage.knowledge-bases.documents.store', [
                'knowledgeBase' => $this->kb->id,
            ]),
            ['files' => [$file], 'group_id' => $group->id]
        )
        ->assertRedirect();

    $document = KnowledgeDocument::query()->firstOrFail();
    expect($document->group_id)->toBe($group->id);
});

test('不属于当前知识库的分组返回校验错误', function () {
    $otherKb = KnowledgeBase::factory()->create([
    ]);
    $foreignGroup = KnowledgeGroup::query()->create([
        'knowledge_base_id' => $otherKb->id,
        'name' => '其它分组',
        'sort_order' => 0,
    ]);

    $file = UploadedFile::fake()->createWithContent('intro.md', '# 简介');

    $this->actingAs($this->user)
        ->from(route('app.manage.knowledge-bases.index', []))
        ->post(
            route('app.manage.knowledge-bases.documents.store', [
                'knowledgeBase' => $this->kb->id,
            ]),
            ['files' => [$file], 'group_id' => $foreignGroup->id]
        )
        ->assertSessionHasErrors(['group_id']);

    expect(KnowledgeDocument::query()->count())->toBe(0);
});

test('不在允许列表中的扩展名会被拒绝', function () {
    $file = UploadedFile::fake()->createWithContent('not-supported.exe', 'hello');

    $this->actingAs($this->user)
        ->from(route('app.manage.knowledge-bases.index', []))
        ->post(
            route('app.manage.knowledge-bases.documents.store', [
                'knowledgeBase' => $this->kb->id,
            ]),
            ['files' => [$file]]
        )
        ->assertSessionHasErrors();

    expect(KnowledgeDocument::query()->count())->toBe(0);
});

test('未提供文件时返回字段级校验错误', function () {
    $this->actingAs($this->user)
        ->from(route('app.manage.knowledge-bases.index', []))
        ->post(
            route('app.manage.knowledge-bases.documents.store', [
                'knowledgeBase' => $this->kb->id,
            ]),
            []
        )
        ->assertSessionHasErrors(['files']);
});

test('删除文档将其从数据库移除', function () {
    /** @var KnowledgeDocument $document */
    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
    ]);

    $this->actingAs($this->user)
        ->delete(
            route('app.manage.knowledge-bases.documents.destroy', [
                'knowledgeBase' => $this->kb->id,
                'document' => $document->id,
            ])
        )
        ->assertRedirect();

    expect(KnowledgeDocument::query()->whereKey($document->id)->exists())->toBeFalse();
});

test('删除上传文档会同步删除原文件附件', function () {
    fakeAttachmentStorage();

    $file = UploadedFile::fake()->createWithContent('cleanup.md', '# Cleanup');

    $this->actingAs($this->user)
        ->post(
            route('app.manage.knowledge-bases.documents.store', [
                'knowledgeBase' => $this->kb->id,
            ]),
            ['files' => [$file]]
        )
        ->assertRedirect();

    $document = KnowledgeDocument::query()->firstOrFail();
    $attachment = $document->originalFile()->firstOrFail();

    $this->actingAs($this->user)
        ->delete(
            route('app.manage.knowledge-bases.documents.destroy', [
                'knowledgeBase' => $this->kb->id,
                'document' => $document->id,
            ])
        )
        ->assertRedirect();

    expect(KnowledgeDocument::query()->whereKey($document->id)->exists())->toBeFalse()
        ->and(Attachment::query()->whereKey($attachment->id)->exists())->toBeFalse();

    $attachment->filesystem()->assertMissing($attachment->object_key);
});

test('删除文档会一并清空其知识节点', function () {
    /** @var KnowledgeDocument $document */
    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
    ]);

    KnowledgeNode::query()->create([
        'knowledge_base_id' => (string) $this->kb->id,
        'document_id' => (string) $document->id,
        'strategy' => KnowledgeIndexingStrategy::Vector,
        'level' => 0,
        'kind' => KnowledgeNodeKind::Segment,
        'content' => '残留节点',
        'content_format' => 'markdown',
        'embedding_dim' => 0,
    ]);

    $this->actingAs($this->user)
        ->delete(
            route('app.manage.knowledge-bases.documents.destroy', [
                'knowledgeBase' => $this->kb->id,
                'document' => $document->id,
            ])
        )
        ->assertRedirect();

    expect(KnowledgeNode::query()->where('document_id', (string) $document->id)->exists())->toBeFalse();
});

test('当选中知识库 + 分组时，文档列表只返回该范围下的文档', function () {
    $defaultGroup = $this->kb->defaultDocumentGroup()->firstOrFail();
    $group = KnowledgeGroup::query()->create([
        'knowledge_base_id' => $this->kb->id,
        'name' => '使用手册',
        'sort_order' => 0,
    ]);

    KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'group_id' => $defaultGroup->id,
        'original_filename' => 'default-group-doc.md',
    ]);
    KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'group_id' => $group->id,
        'original_filename' => 'group-doc.md',
    ]);

    $this->actingAs($this->user)
        ->get(
            route('app.manage.knowledge-bases.index', [
                'kb' => $this->kb->id,
                'group' => $group->id,
            ])
        )
        ->assertInertia(fn ($page) => $page
            ->component('knowledgeBase/List')
            ->where('selected_group_id', $group->id)
            ->has('document_list', 1)
            ->where('document_list.0.original_filename', 'group-doc.md')
            ->where('document_list.0.status', KnowledgeDocumentStatus::Pending->value)
            ->where('document_list.0.status_label', __('knowledge_base.documents.statuses.pending'))
        );
});

test('当选中父分组时，文档列表包含其子分组文档', function () {
    $parentGroup = KnowledgeGroup::query()->create([
        'knowledge_base_id' => $this->kb->id,
        'name' => '产品手册',
        'sort_order' => 1,
    ]);
    $childGroup = KnowledgeGroup::query()->create([
        'knowledge_base_id' => $this->kb->id,
        'parent_id' => $parentGroup->id,
        'name' => '快速入门',
        'sort_order' => 1,
    ]);
    $siblingGroup = KnowledgeGroup::query()->create([
        'knowledge_base_id' => $this->kb->id,
        'name' => '售后政策',
        'sort_order' => 2,
    ]);

    KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'group_id' => $parentGroup->id,
        'original_filename' => 'parent-group-doc.md',
        'created_at' => now()->subMinutes(2),
    ]);
    KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'group_id' => $childGroup->id,
        'original_filename' => 'child-group-doc.md',
        'created_at' => now()->subMinute(),
    ]);
    KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'group_id' => $siblingGroup->id,
        'original_filename' => 'sibling-group-doc.md',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(
            route('app.manage.knowledge-bases.index', [
                'kb' => $this->kb->id,
                'group' => $parentGroup->id,
            ])
        )
        ->assertInertia(fn ($page) => $page
            ->component('knowledgeBase/List')
            ->where('selected_group_id', $parentGroup->id)
            ->has('document_list', 2)
            ->where('document_list.0.original_filename', 'child-group-doc.md')
            ->where('document_list.1.original_filename', 'parent-group-doc.md')
        );
});

test('当只选中知识库时，文档列表返回全部分组下的文档', function () {
    $defaultGroup = $this->kb->defaultDocumentGroup()->firstOrFail();
    $group = KnowledgeGroup::query()->create([
        'knowledge_base_id' => $this->kb->id,
        'name' => '使用手册',
        'sort_order' => 1,
    ]);

    KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'group_id' => $defaultGroup->id,
        'original_filename' => 'default-group-doc.md',
    ]);
    KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'group_id' => $group->id,
        'original_filename' => 'group-doc.md',
    ]);

    $this->actingAs($this->user)
        ->get(
            route('app.manage.knowledge-bases.index', [
                'kb' => $this->kb->id,
            ])
        )
        ->assertInertia(fn ($page) => $page
            ->component('knowledgeBase/List')
            ->where('selected_group_id', null)
            ->has('document_list', 2)
        );
});

test('可以按文档状态筛选知识库文档列表', function () {
    KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'original_filename' => 'pending-doc.md',
        'status' => KnowledgeDocumentStatus::Pending,
    ]);
    KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'original_filename' => 'failed-doc.md',
        'status' => KnowledgeDocumentStatus::Failed,
        'error_message' => '解析失败',
    ]);

    $this->actingAs($this->user)
        ->get(
            route('app.manage.knowledge-bases.index', [
                'kb' => $this->kb->id,
                'status' => KnowledgeDocumentStatus::Failed->value,
            ])
        )
        ->assertInertia(fn ($page) => $page
            ->component('knowledgeBase/List')
            ->where('current_status', KnowledgeDocumentStatus::Failed->value)
            ->has('document_list', 1)
            ->where('document_list.0.original_filename', 'failed-doc.md')
            ->where('document_list.0.status', KnowledgeDocumentStatus::Failed->value)
        );
});

test('处理中的状态筛选包含读取、准备和处理阶段', function () {
    foreach ([
        KnowledgeDocumentStatus::Pending,
        KnowledgeDocumentStatus::Parsing,
        KnowledgeDocumentStatus::Parsed,
        KnowledgeDocumentStatus::Indexing,
        KnowledgeDocumentStatus::Indexed,
        KnowledgeDocumentStatus::Failed,
    ] as $status) {
        KnowledgeDocument::factory()->create([
            'knowledge_base_id' => $this->kb->id,
            'original_filename' => $status->value.'.md',
            'status' => $status,
        ]);
    }

    $this->actingAs($this->user)
        ->get(route('app.manage.knowledge-bases.index', [
            'kb' => $this->kb->id,
            'status' => 'processing',
        ]))
        ->assertInertia(fn ($page) => $page
            ->where('current_status', 'processing')
            ->has('document_list', 3)
            ->where(
                'document_list',
                fn ($documents) => collect($documents)
                    ->pluck('status')
                    ->sort()
                    ->values()
                    ->all() === [
                        KnowledgeDocumentStatus::Indexing->value,
                        KnowledgeDocumentStatus::Parsed->value,
                        KnowledgeDocumentStatus::Parsing->value,
                    ]
            )
        );
});

test('可以按文件名搜索知识库文档列表', function () {
    KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'original_filename' => 'refund-policy.md',
    ]);
    KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'original_filename' => 'installation-guide.md',
    ]);

    $this->actingAs($this->user)
        ->get(
            route('app.manage.knowledge-bases.index', [
                'kb' => $this->kb->id,
                'search' => 'refund',
            ])
        )
        ->assertInertia(fn ($page) => $page
            ->component('knowledgeBase/List')
            ->where('search', 'refund')
            ->has('document_list', 1)
            ->where('document_list.0.original_filename', 'refund-policy.md')
        );
});

test('文档列表按每页 10 条分页，并返回分页元信息', function () {
    foreach (range(1, 25) as $index) {
        KnowledgeDocument::factory()->create([
            'knowledge_base_id' => $this->kb->id,
            'original_filename' => sprintf('doc-%02d.md', $index),
            'created_at' => now()->subSeconds(25 - $index),
        ]);
    }

    $this->actingAs($this->user)
        ->get(
            route('app.manage.knowledge-bases.index', [
                'kb' => $this->kb->id,
            ])
        )
        ->assertInertia(fn ($page) => $page
            ->component('knowledgeBase/List')
            ->has('document_list', 10)
            ->where('document_list_pagination.current_page', 1)
            ->where('document_list_pagination.last_page', 3)
            ->where('document_list_pagination.per_page', 10)
            ->where('document_list_pagination.total', 25)
            ->where('document_list.0.original_filename', 'doc-25.md')
        );

    $this->actingAs($this->user)
        ->get(
            route('app.manage.knowledge-bases.index', [
                'kb' => $this->kb->id,
                'page' => 2,
            ])
        )
        ->assertInertia(fn ($page) => $page
            ->component('knowledgeBase/List')
            ->has('document_list', 10)
            ->where('document_list_pagination.current_page', 2)
            ->where('document_list_pagination.last_page', 3)
            ->where('document_list_pagination.total', 25)
            ->where('document_list.0.original_filename', 'doc-15.md')
            ->where('document_list.9.original_filename', 'doc-06.md')
        );
});

test('未选中知识库时分页元信息为空集合', function () {
    KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
    ]);

    $this->actingAs($this->user)
        ->get(
            route('app.manage.knowledge-bases.index', [
            ])
        )
        ->assertInertia(fn ($page) => $page
            ->component('knowledgeBase/List')
            ->where('selected_knowledge_base', null)
            ->has('document_list', 0)
            ->where('document_list_pagination.current_page', 1)
            ->where('document_list_pagination.last_page', 1)
            ->where('document_list_pagination.total', 0)
        );
});

test('所有者可以将文档移动到另一个分组', function () {
    $defaultGroup = $this->kb->defaultDocumentGroup()->firstOrFail();
    $targetGroup = KnowledgeGroup::query()->create([
        'knowledge_base_id' => $this->kb->id,
        'name' => '使用手册',
        'sort_order' => 1,
    ]);
    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'group_id' => $defaultGroup->id,
    ]);

    $this->actingAs($this->user)
        ->put(
            route('app.manage.knowledge-bases.documents.move', [
                'knowledgeBase' => $this->kb->id,
                'document' => $document->id,
            ]),
            ['group_id' => $targetGroup->id]
        )
        ->assertRedirect();

    expect($document->fresh()->group_id)->toBe((string) $targetGroup->id);
});

test('移动文档时不能使用其它知识库的分组', function () {
    $otherKb = KnowledgeBase::factory()->create([
    ]);
    $foreignGroup = $otherKb->defaultDocumentGroup()->firstOrFail();
    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
    ]);

    $this->actingAs($this->user)
        ->from(route('app.manage.knowledge-bases.index', []))
        ->put(
            route('app.manage.knowledge-bases.documents.move', [
                'knowledgeBase' => $this->kb->id,
                'document' => $document->id,
            ]),
            ['group_id' => $foreignGroup->id]
        )
        ->assertSessionHasErrors(['group_id']);
});

test('所有者可以在知识库下手动添加 Markdown 文档', function () {
    $content = "# 退款政策\n\n这是一份手动录入的文档，仅供测试。";

    $this->actingAs($this->user)
        ->post(
            route('app.manage.knowledge-bases.documents.manual.store', [
                'knowledgeBase' => $this->kb->id,
            ]),
            [
                'title' => '退款政策',
                'content' => $content,
            ]
        )
        ->assertRedirect();

    $document = KnowledgeDocument::query()->firstOrFail();
    $defaultGroup = $this->kb->defaultDocumentGroup()->firstOrFail();

    expect($document->source_type)->toBe(KnowledgeDocumentSourceType::Manual)
        ->and($document->original_filename)->toBe('退款政策')
        ->and($document->mime_type)->toBe('text/markdown')
        ->and($document->extension)->toBe('md')
        ->and($document->content)->toBe($content)
        ->and($document->byte_size)->toBe(strlen($content))
        ->and($document->checksum_sha256)->toBe(hash('sha256', $content))
        ->and($document->status)->toBe(KnowledgeDocumentStatus::Pending)
        ->and($document->group_id)->toBe((string) $defaultGroup->id)
        ->and($document->uploaded_by_user_id)->toBe((string) $this->user->id)
        ->and($document->originalFile)->toBeNull();
});

test('手动添加文档可以指定分组', function () {
    $group = KnowledgeGroup::query()->create([
        'knowledge_base_id' => $this->kb->id,
        'name' => '使用手册',
        'sort_order' => 0,
    ]);

    $this->actingAs($this->user)
        ->post(
            route('app.manage.knowledge-bases.documents.manual.store', [
                'knowledgeBase' => $this->kb->id,
            ]),
            [
                'title' => '安装指南',
                'content' => '安装步骤',
                'group_id' => $group->id,
            ]
        )
        ->assertRedirect();

    $document = KnowledgeDocument::query()->firstOrFail();
    expect($document->group_id)->toBe((string) $group->id);
});

test('问答知识库不能手动添加普通文档', function () {
    $qaKnowledgeBase = KnowledgeBase::factory()->create([
        'category' => KnowledgeBaseCategory::Qa->value,
    ]);

    $this->actingAs($this->user)
        ->from(route('app.manage.knowledge-bases.index', []))
        ->withHeader('X-Inertia', 'true')
        ->post(
            route('app.manage.knowledge-bases.documents.manual.store', [
                'knowledgeBase' => $qaKnowledgeBase->id,
            ]),
            ['title' => '普通文档', 'content' => '内容']
        )
        ->assertSessionHasErrors('toast');

    expect(KnowledgeDocument::query()->where('knowledge_base_id', $qaKnowledgeBase->id)->count())->toBe(0);
});

test('手动添加文档校验标题和正文必填', function () {
    $this->actingAs($this->user)
        ->from(route('app.manage.knowledge-bases.index', []))
        ->post(
            route('app.manage.knowledge-bases.documents.manual.store', [
                'knowledgeBase' => $this->kb->id,
            ]),
            []
        )
        ->assertSessionHasErrors(['title', 'content']);

    expect(KnowledgeDocument::query()->count())->toBe(0);
});

test('手动添加文档时不属于当前知识库的分组返回校验错误', function () {
    $otherKb = KnowledgeBase::factory()->create([
    ]);
    $foreignGroup = $otherKb->defaultDocumentGroup()->firstOrFail();

    $this->actingAs($this->user)
        ->from(route('app.manage.knowledge-bases.index', []))
        ->post(
            route('app.manage.knowledge-bases.documents.manual.store', [
                'knowledgeBase' => $this->kb->id,
            ]),
            [
                'title' => '简介',
                'content' => '内容',
                'group_id' => $foreignGroup->id,
            ]
        )
        ->assertSessionHasErrors(['group_id']);

    expect(KnowledgeDocument::query()->where('knowledge_base_id', $this->kb->id)->count())->toBe(0);
});

test('可以预览手动添加的文档（流式返回 Markdown 正文）', function () {
    $content = "# 手册\n这是手动录入的正文。";

    $this->actingAs($this->user)
        ->post(
            route('app.manage.knowledge-bases.documents.manual.store', [
                'knowledgeBase' => $this->kb->id,
            ]),
            ['title' => '手册', 'content' => $content]
        )
        ->assertRedirect();

    $document = KnowledgeDocument::query()->firstOrFail();

    $response = $this->actingAs($this->user)
        ->get(
            route('app.manage.knowledge-bases.documents.preview-file', [
                'knowledgeBase' => $this->kb->id,
                'document' => $document->id,
            ])
        );

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toStartWith('text/markdown')
        ->and($response->headers->get('cache-control'))->toContain('no-store')
        ->and($response->streamedContent())->toBe($content);
});

test('手动文档预览按 Markdown 正文返回', function () {
    $content = "# 手册\n这是手动录入的正文。";

    /** @var KnowledgeDocument $document */
    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'source_type' => KnowledgeDocumentSourceType::Manual,
        'original_filename' => '手册.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'content' => $content,
        'byte_size' => strlen($content),
    ]);

    $response = $this->actingAs($this->user)
        ->get(
            route('app.manage.knowledge-bases.documents.preview-file', [
                'knowledgeBase' => $this->kb->id,
                'document' => $document->id,
            ])
        );

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toStartWith('text/markdown')
        ->and($response->streamedContent())->toBe($content);
});

test('编辑手动添加的文档会更新标题、正文、字节数和校验和', function () {
    /** @var KnowledgeDocument $document */
    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'source_type' => KnowledgeDocumentSourceType::Manual,
        'original_filename' => '旧标题',
        'mime_type' => 'text/markdown',
        'extension' => 'md',
        'content' => '旧正文',
        'byte_size' => strlen('旧正文'),
    ]);

    $newContent = "# 新标题\n新的正文内容";

    $this->actingAs($this->user)
        ->put(
            route('app.manage.knowledge-bases.documents.manual.update', [
                'knowledgeBase' => $this->kb->id,
                'document' => $document->id,
            ]),
            ['title' => '新标题', 'content' => $newContent]
        )
        ->assertRedirect();

    $document->refresh();
    expect($document->original_filename)->toBe('新标题')
        ->and($document->content)->toBe($newContent)
        ->and($document->byte_size)->toBe(strlen($newContent))
        ->and($document->checksum_sha256)->toBe(hash('sha256', $newContent))
        ->and($document->extension)->toBe('md');
});

test('编辑接口对上传类型的文档抛出业务异常', function () {
    /** @var KnowledgeDocument $document */
    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'source_type' => KnowledgeDocumentSourceType::Upload,
    ]);

    UpdateManualKnowledgeDocumentAction::run(
        $document,
        FormUpdateManualKnowledgeDocumentData::from(['title' => 'x', 'content' => 'y'])
    );
})->throws(BusinessException::class);

test('编辑手动文档时校验标题和正文必填', function () {
    /** @var KnowledgeDocument $document */
    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'source_type' => KnowledgeDocumentSourceType::Manual,
    ]);

    $this->actingAs($this->user)
        ->from(route('app.manage.knowledge-bases.index', []))
        ->put(
            route('app.manage.knowledge-bases.documents.manual.update', [
                'knowledgeBase' => $this->kb->id,
                'document' => $document->id,
            ]),
            []
        )
        ->assertSessionHasErrors(['title', 'content']);
});

test('文档列表项会带上 source_type 标识', function () {
    KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'source_type' => KnowledgeDocumentSourceType::Manual,
        'original_filename' => '手动文档',
        'created_at' => now()->subMinute(),
    ]);
    KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'source_type' => KnowledgeDocumentSourceType::Upload,
        'original_filename' => '上传文档.md',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(
            route('app.manage.knowledge-bases.index', [
                'kb' => $this->kb->id,
            ])
        )
        ->assertInertia(fn ($page) => $page
            ->component('knowledgeBase/List')
            ->has('document_list', 2)
            ->where('document_list.0.source_type', KnowledgeDocumentSourceType::Upload->value)
            ->where('document_list.1.source_type', KnowledgeDocumentSourceType::Manual->value)
        );
});

test('删除手动添加的文档不会影响附件表', function () {
    /** @var KnowledgeDocument $document */
    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $this->kb->id,
        'source_type' => KnowledgeDocumentSourceType::Manual,
    ]);

    $this->actingAs($this->user)
        ->delete(
            route('app.manage.knowledge-bases.documents.destroy', [
                'knowledgeBase' => $this->kb->id,
                'document' => $document->id,
            ])
        )
        ->assertRedirect();

    expect(KnowledgeDocument::query()->whereKey($document->id)->exists())->toBeFalse()
        ->and(Attachment::query()->count())->toBe(0);
});
