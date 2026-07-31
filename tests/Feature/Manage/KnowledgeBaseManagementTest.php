<?php

use App\Actions\KnowledgeBase\DeleteKnowledgeBaseAction;
use App\Enums\KnowledgeIndexingStrategy;
use App\Enums\KnowledgeNodeKind;
use App\Exceptions\BusinessException;
use App\Models\Attachment;
use App\Models\ExperienceExtraction;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeGroup;
use App\Models\KnowledgeNode;
use App\Models\ReceptionPlan;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
});

/**
 * 创建知识库图标测试附件。
 */
function createKnowledgeBaseTestAttachment(array $attributes = []): Attachment
{
    /** @var GeneralSettings $app */
    $app = test()->instance;

    return Attachment::factory()->create(array_merge([
        'uploaded_by_user_id' => test()->user->id,
        'object_key' => 'system/knowledge_base_avatar/'.Str::lower(Str::random(8)).'.png',
        'original_name' => 'knowledge-base.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
        'byte_size' => 1024,
        'purpose' => 'avatar',
        'status' => 'uploaded',
    ], $attributes));
}

test('所有者可以查看知识库列表页面', function () {
    KnowledgeBase::factory()->create([
        'name' => '售后政策库',
        'description' => '退款、退货和换货规则',
    ]);

    $this->actingAs($this->user)
        ->get(route('app.manage.knowledge-bases.index', []))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('knowledgeBase/List')
            ->has('knowledge_base_list', 1)
            ->where('knowledge_base_list.0.name', '售后政策库')
            ->where('knowledge_base_list.0.avatar_id', null)
            ->where('knowledge_base_list.0.avatar_url', null)
            ->where('selected_knowledge_base', null)
        );
});

test('知识库列表按创建时间从旧到新排列', function () {
    KnowledgeBase::factory()->create([
        'name' => '旧知识库',
        'created_at' => now()->subMinutes(2),
    ]);
    KnowledgeBase::factory()->create([
        'name' => '中间知识库',
        'created_at' => now()->subMinute(),
    ]);
    KnowledgeBase::factory()->create([
        'name' => '新知识库',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('app.manage.knowledge-bases.index', []))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('knowledgeBase/List')
            ->where('knowledge_base_list.0.name', '旧知识库')
            ->where('knowledge_base_list.1.name', '中间知识库')
            ->where('knowledge_base_list.2.name', '新知识库')
        );
});

test('所有者可以打开创建和编辑知识库页面', function () {
    $knowledgeBase = KnowledgeBase::factory()->create([
        'name' => '产品知识库',
    ]);

    $this->actingAs($this->user)
        ->get(route('app.manage.knowledge-bases.create', []))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('knowledgeBase/Create')
        );

    $this->actingAs($this->user)
        ->get(route('app.manage.knowledge-bases.edit', [
            'knowledgeBase' => $knowledgeBase->id,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('knowledgeBase/Edit')
            ->where('knowledge_base_form.id', (string) $knowledgeBase->id)
            ->where('knowledge_base_form.name', '产品知识库')
        );
});

test('所有者可以创建更新并删除知识库', function () {
    fakeAttachmentStorage();
    $avatar = createKnowledgeBaseTestAttachment();
    $updatedAvatar = createKnowledgeBaseTestAttachment();

    $response = $this->actingAs($this->user)
        ->post(route('app.manage.knowledge-bases.store', []), [
            'name' => '帮助中心知识库',
            'avatar_id' => $avatar->id,
            'description' => '常见问题和操作说明',
            'category' => 'standard',
        ]);

    $knowledgeBase = KnowledgeBase::query()
        ->firstOrFail();

    $response->assertRedirect(route('app.manage.knowledge-bases.index', [
        'kb' => $knowledgeBase->id,
    ]));

    expect($knowledgeBase->name)->toBe('帮助中心知识库')
        ->and($knowledgeBase->description)->toBe('常见问题和操作说明')
        ->and($knowledgeBase->avatar_id)->toBe($avatar->id)
        ->and($avatar->fresh()->attachable_id)->toBe($knowledgeBase->id);

    $defaultGroup = KnowledgeGroup::query()
        ->where('knowledge_base_id', $knowledgeBase->id)
        ->where('is_default', true)
        ->firstOrFail();

    expect($defaultGroup->name)->toBe(KnowledgeBase::DEFAULT_GROUP_NAME)
        ->and($defaultGroup->parent_id)->toBeNull();

    $this->actingAs($this->user)
        ->put(route('app.manage.knowledge-bases.update', [
            'knowledgeBase' => $knowledgeBase->id,
        ]), [
            'name' => '帮助中心知识库 Plus',
            'avatar_id' => $updatedAvatar->id,
            'description' => '更新后的常见问题',
            'category' => 'standard',
        ])
        ->assertRedirect();

    $knowledgeBase->refresh();
    expect($knowledgeBase->name)->toBe('帮助中心知识库 Plus')
        ->and($knowledgeBase->description)->toBe('更新后的常见问题')
        ->and($knowledgeBase->avatar_id)->toBe($updatedAvatar->id)
        ->and($updatedAvatar->fresh()->attachable_id)->toBe($knowledgeBase->id);

    $this->actingAs($this->user)
        ->delete(route('app.manage.knowledge-bases.destroy', [
            'knowledgeBase' => $knowledgeBase->id,
        ]))
        ->assertRedirect(route('app.manage.knowledge-bases.index', []));

    $this->assertDatabaseMissing('knowledge_bases', [
        'id' => $knowledgeBase->id,
    ]);
    $this->assertDatabaseMissing('knowledge_groups', [
        'id' => $defaultGroup->id,
    ]);
});

test('删除知识库会一并清空其知识节点', function () {
    /** @var KnowledgeBase $knowledgeBase */
    $knowledgeBase = KnowledgeBase::factory()->create([
        'name' => '将被删除的知识库',
    ]);
    /** @var KnowledgeDocument $document */
    $document = KnowledgeDocument::factory()->create([
        'knowledge_base_id' => $knowledgeBase->id,
    ]);

    KnowledgeNode::query()->create([
        'knowledge_base_id' => (string) $knowledgeBase->id,
        'document_id' => (string) $document->id,
        'strategy' => KnowledgeIndexingStrategy::Vector,
        'level' => 0,
        'kind' => KnowledgeNodeKind::Segment,
        'content' => '残留节点',
        'content_format' => 'markdown',
        'embedding_dim' => 0,
    ]);

    $this->actingAs($this->user)
        ->delete(route('app.manage.knowledge-bases.destroy', [
            'knowledgeBase' => $knowledgeBase->id,
        ]))
        ->assertRedirect();

    expect(KnowledgeNode::query()->where('knowledge_base_id', (string) $knowledgeBase->id)->exists())->toBeFalse();
});

test('删除知识库会同步删除上传文档的附件和对象文件', function () {
    fakeAttachmentStorage();
    Bus::fake();

    $avatar = createKnowledgeBaseTestAttachment();
    $avatar->filesystem()->put($avatar->object_key, 'avatar');
    $knowledgeBase = KnowledgeBase::factory()->create([
        'name' => '待清理文档知识库',
        'avatar_id' => $avatar->id,
    ]);
    $avatar->update([
        'attachable_type' => KnowledgeBase::class,
        'attachable_id' => $knowledgeBase->id,
    ]);
    $file = UploadedFile::fake()->createWithContent('cleanup.md', '# 待清理内容');

    $this->actingAs($this->user)
        ->post(route('app.manage.knowledge-bases.documents.store', [
            'knowledgeBase' => $knowledgeBase->id,
        ]), ['files' => [$file]])
        ->assertRedirect();

    $document = KnowledgeDocument::query()
        ->where('knowledge_base_id', $knowledgeBase->id)
        ->firstOrFail();
    $attachment = $document->originalFile()->firstOrFail();

    $this->actingAs($this->user)
        ->delete(route('app.manage.knowledge-bases.destroy', [
            'knowledgeBase' => $knowledgeBase->id,
        ]))
        ->assertRedirect();

    expect(KnowledgeDocument::query()->whereKey($document->id)->exists())->toBeFalse()
        ->and(Attachment::query()->whereKey($attachment->id)->exists())->toBeFalse()
        ->and(Attachment::query()->whereKey($avatar->id)->exists())->toBeFalse();

    $attachment->filesystem()->assertMissing($attachment->object_key)
        ->assertMissing($avatar->object_key);
});

test('接待方案正在使用的知识库不能删除', function () {
    $knowledgeBase = KnowledgeBase::factory()->create();
    $plan = ReceptionPlan::factory()->create();
    $plan->knowledgeBases()->attach($knowledgeBase);

    $this->actingAs($this->user)
        ->from(route('app.manage.knowledge-bases.index'))
        ->withHeader('X-Inertia', 'true')
        ->delete(route('app.manage.knowledge-bases.destroy', [
            'knowledgeBase' => $knowledgeBase->id,
        ]))
        ->assertSessionHasErrors(['toast']);

    expect(KnowledgeBase::query()->whereKey($knowledgeBase->id)->exists())->toBeTrue();
});

test('经验提炼正在使用的知识库不能删除', function () {
    $knowledgeBase = KnowledgeBase::factory()->qa()->create();
    ExperienceExtraction::factory()->create([
        'knowledge_base_id' => $knowledgeBase->id,
    ]);

    expect(fn () => app(DeleteKnowledgeBaseAction::class)->handle($knowledgeBase))
        ->toThrow(BusinessException::class);

    expect(KnowledgeBase::query()->whereKey($knowledgeBase->id)->exists())->toBeTrue();
});

test('知识库名称在应用内必须唯一', function () {
    KnowledgeBase::factory()->create([
        'name' => '重复名称',
    ]);

    $this->actingAs($this->user)
        ->post(route('app.manage.knowledge-bases.store', []), [
            'name' => '重复名称',
            'description' => '',
            'category' => 'standard',
        ])
        ->assertSessionHasErrors(['name']);
});

test('知识库头像需要来自当前应用可用附件', function () {
    $otherKnowledgeBase = KnowledgeBase::factory()->create([
    ]);
    $foreignAttachment = createKnowledgeBaseTestAttachment([
        'attachable_type' => KnowledgeBase::class,
        'attachable_id' => $otherKnowledgeBase->id,
    ]);

    $this->actingAs($this->user)
        ->post(route('app.manage.knowledge-bases.store', []), [
            'name' => '尝试占用头像',
            'avatar_id' => $foreignAttachment->id,
            'description' => '',
            'category' => 'standard',
        ])
        ->assertUnprocessable()
        ->assertJson([
            'message' => __('knowledge_base.messages.invalid_attachment'),
        ]);

    expect($foreignAttachment->fresh()->attachable_id)->toBe($otherKnowledgeBase->id);
});
