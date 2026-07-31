<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
    // 标签必属于一个组，管理页用例统一在该组下创建/更新标签。
    $this->group = TagGroup::factory()->contact()->create([
        'name' => '客户价值',
    ]);
});

test('已认证用户可以查看标签列表页面', function () {
    Tag::factory()->forGroup($this->group)->create([
        'name' => 'VIP',
        'color' => '#ff0000',
        'description' => '重要客户',
    ]);

    $this->actingAs($this->user)
        ->get(route('app.manage.tags.index', []))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tags/Index')
            ->has('tag_group_list', 1)
            ->where('tag_group_list.0.name', '客户价值')
            ->where('tag_group_list.0.scope', 'contact')
            ->has('tag_group_list.0.tags', 1)
            ->where('tag_group_list.0.tags.0.name', 'VIP')
            ->etc()
        );
});

test('已认证用户可以查看标签回收站页面', function () {
    $deletedTag = Tag::factory()->create([
        'name' => 'Deleted Tag',
    ]);
    $deletedTag->delete();

    Tag::factory()->create([
        'name' => 'Active Tag',
    ]);

    $this->actingAs($this->user)
        ->get(route('app.manage.tags.trash', []))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('tags/Trash')
            ->has('trashed_tag_list', 1)
            ->where('trashed_tag_list.0.id', (string) $deletedTag->id)
            ->where('trashed_tag_list.0.name', 'Deleted Tag')
            ->where('trashed_tag_list_pagination.total', 1)
            ->etc()
        );
});

test('可以创建标签', function () {
    $this->actingAs($this->user)
        ->from(route('app.manage.tags.index', []))
        ->post(route('app.manage.tags.store', []), [
            'tag_group_id' => $this->group->id,
            'name' => '  新标签  ',
            'color' => '#00ff00',
            'description' => '用于测试',
        ])
        ->assertRedirect(route('app.manage.tags.index', []));

    $tag = Tag::query()->where('name', '新标签')->first();
    expect($tag)->not()->toBeNull();
    expect($tag->name)->toBe('新标签');
    expect($tag->normalized_name)->toBe('新标签');
    expect($tag->tag_group_id)->toBe($this->group->id);
    expect($tag->source->value)->toBe('manual');
    expect($tag->is_locked)->toBeFalse();
    expect($tag->created_by_user_id)->toBe($this->user->id);
});

test('系统成员可以创建标签', function () {
    $member = User::factory()->create();
    attachMember($member);

    $this->actingAs($member)
        ->from(route('app.manage.tags.index', []))
        ->post(route('app.manage.tags.store', []), [
            'tag_group_id' => $this->group->id,
            'name' => '新成员标签',
            'color' => '#00ff00',
            'description' => null,
        ])
        ->assertRedirect(route('app.manage.tags.index', []));
});

test('normalized_name自动生成在创建和更新', function () {
    $tag = Tag::factory()->create([
        'name' => '  VIP Tag  ',
    ]);

    expect($tag->name)->toBe('VIP Tag');
    expect($tag->normalized_name)->toBe('vip tag');

    $tag->update(['name' => '  Updated TAG  ']);
    expect($tag->fresh()->name)->toBe('Updated TAG');
    expect($tag->fresh()->normalized_name)->toBe('updated tag');
});

test('不能创建重复标签名称不区分大小写', function () {
    Tag::factory()->forGroup($this->group)->create([
        'name' => 'VIP',
    ]);

    $this->actingAs($this->user)
        ->post(route('app.manage.tags.store', []), [
            'tag_group_id' => $this->group->id,
            'name' => 'vip',
        ])
        ->assertSessionHasErrors(['name']);
});

test('不同标签组允许创建相同标签名称', function () {
    $otherGroup = TagGroup::factory()->contact()->create([
        'name' => '客户阶段',
    ]);

    Tag::factory()->forGroup($this->group)->create([
        'name' => 'VIP',
    ]);

    $this->actingAs($this->user)
        ->post(route('app.manage.tags.store', []), [
            'tag_group_id' => $otherGroup->id,
            'name' => 'vip',
        ])
        ->assertRedirect();

    expect(Tag::query()

        ->where('normalized_name', 'vip')
        ->count()
    )->toBe(2);
});

test('可以创建标签且名称与软删除标签相同', function () {
    $tag = Tag::factory()->forGroup($this->group)->create([
        'name' => 'Deleted',
    ]);
    $tag->delete();

    $this->actingAs($this->user)
        ->post(route('app.manage.tags.store', []), [
            'tag_group_id' => $this->group->id,
            'name' => 'Deleted',
        ])
        ->assertRedirect();

    expect(Tag::query()

        ->where('name', 'Deleted')
        ->whereNull('deleted_at')
        ->count()
    )->toBe(1);
});

test('可以更新标签', function () {
    $tag = Tag::factory()->forGroup($this->group)->create([
        'name' => '旧名称',
        'color' => '#111111',
    ]);

    $this->actingAs($this->user)
        ->from(route('app.manage.tags.index', []))
        ->put(route('app.manage.tags.update', ['id' => $tag->id]), [
            'tag_group_id' => $this->group->id,
            'name' => '  新名称  ',
            'color' => '#222222',
            'description' => '更新描述',
        ])
        ->assertRedirect(route('app.manage.tags.index', []));

    $tag->refresh();
    expect($tag->name)->toBe('新名称');
    expect($tag->color)->toBe('#222222');
    expect($tag->description)->toBe('更新描述');
    expect($tag->updated_by_user_id)->toBe($this->user->id);
});

test('不能删除锁定标签', function () {
    $tag = Tag::factory()->locked()->create([
    ]);

    $this->actingAs($this->user)
        ->delete(route('app.manage.tags.destroy', ['id' => $tag->id]))
        ->assertUnprocessable();

    expect(Tag::query()->find($tag->id))->not()->toBeNull();
});

test('可以删除标签', function () {
    $tag = Tag::factory()->create([
    ]);

    $this->actingAs($this->user)
        ->from(route('app.manage.tags.index', []))
        ->delete(route('app.manage.tags.destroy', ['id' => $tag->id]))
        ->assertRedirect(route('app.manage.tags.index', []));

    $this->assertSoftDeleted('tags', ['id' => $tag->id]);
});

test('可以恢复已删除标签', function () {
    $tag = Tag::factory()->create([
        'name' => 'Restored',
    ]);
    $tag->delete();

    $this->actingAs($this->user)
        ->put(route('app.manage.tags.restore', ['id' => $tag->id]))
        ->assertRedirect();

    expect(Tag::query()->find($tag->id)->deleted_at)->toBeNull();
});

test('不能恢复标签如果名称冲突并带活跃标签', function () {
    Tag::factory()->forGroup($this->group)->create([
        'name' => 'Conflict',
    ]);

    $trashed = Tag::factory()->forGroup($this->group)->create([
        'name' => 'TrashedConflict',
    ]);
    $trashed->delete();

    DB::table('tags')
        ->where('id', $trashed->id)
        ->update(['normalized_name' => 'conflict']);

    $this->actingAs($this->user)
        ->from(route('app.manage.tags.trash', []))
        ->put(route('app.manage.tags.restore', ['id' => $trashed->id]))
        ->assertRedirect(route('app.manage.tags.trash', []))
        ->assertSessionHasErrors(['tag']);

    expect(Tag::withTrashed()->find($trashed->id)->deleted_at)->not()->toBeNull();
});

test('可以合并标签', function () {
    $target = Tag::factory()->create([
        'name' => 'Target',
    ]);
    $merged = Tag::factory()->create([
        'name' => 'ToMerge',
    ]);

    $contact = Contact::factory()->create([
    ]);

    DB::table('contact_tag_assignments')->insert([
        'tag_id' => $merged->id,
        'contact_id' => $contact->id,
        'source' => 'manual',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->post(route('app.manage.tags.merge', []), [
            'target_tag_id' => $target->id,
            'merged_tag_id' => $merged->id,
        ])
        ->assertRedirect();

    $this->assertSoftDeleted('tags', ['id' => $merged->id]);
    expect(DB::table('contact_tag_assignments')->where('tag_id', $target->id)->where('contact_id', $contact->id)->exists())->toBeTrue();
    expect(DB::table('contact_tag_assignments')->where('tag_id', $merged->id)->exists())->toBeFalse();
});

test('合并标签同时迁移会话维度的标签关联', function () {
    $target = Tag::factory()->create(['name' => 'Target']);
    $merged = Tag::factory()->create(['name' => 'ToMerge']);

    $conversation = Conversation::factory()->create([]);

    DB::table('conversation_tag_assignments')->insert([
        'conversation_id' => $conversation->id,
        'tag_id' => $merged->id,
        'source' => 'manual',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->post(route('app.manage.tags.merge', []), [
            'target_tag_id' => $target->id,
            'merged_tag_id' => $merged->id,
        ])
        ->assertRedirect();

    expect(DB::table('conversation_tag_assignments')->where('tag_id', $target->id)->where('conversation_id', $conversation->id)->exists())->toBeTrue();
    expect(DB::table('conversation_tag_assignments')->where('tag_id', $merged->id)->exists())->toBeFalse();
});

test('不能合并锁定标签为另一个标签', function () {
    $target = Tag::factory()->create([
        'name' => 'Target',
    ]);
    $locked = Tag::factory()->locked()->create([
        'name' => 'Locked',
    ]);

    $this->actingAs($this->user)
        ->from(route('app.manage.tags.index', []))
        ->post(route('app.manage.tags.merge', []), [
            'target_tag_id' => $target->id,
            'merged_tag_id' => $locked->id,
        ])
        ->assertRedirect(route('app.manage.tags.index', []))
        ->assertSessionHasErrors(['merged_tag_id']);

    expect(Tag::query()->find($locked->id))->not()->toBeNull();
});

test('不能合并标签为自身', function () {
    $tag = Tag::factory()->create([
        'name' => 'Self',
    ]);

    $contact = Contact::factory()->create([
    ]);

    DB::table('contact_tag_assignments')->insert([
        'tag_id' => $tag->id,
        'contact_id' => $contact->id,
        'source' => 'manual',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->from(route('app.manage.tags.index', []))
        ->post(route('app.manage.tags.merge', []), [
            'target_tag_id' => $tag->id,
            'merged_tag_id' => $tag->id,
        ])
        ->assertRedirect(route('app.manage.tags.index', []))
        ->assertSessionHasErrors(['merged_tag_id']);

    expect(Tag::query()->find($tag->id))->not()->toBeNull()
        ->and(DB::table('contact_tag_assignments')
            ->where('tag_id', $tag->id)
            ->where('contact_id', $contact->id)
            ->exists())->toBeTrue();
});

test('可以查看标签使用情况', function () {
    $tag = Tag::factory()->create([
    ]);

    $contact = Contact::factory()->create([
    ]);

    DB::table('contact_tag_assignments')->insert([
        'tag_id' => $tag->id,
        'contact_id' => $contact->id,
        'source' => 'manual',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('app.manage.tags.usage', ['id' => $tag->id]))
        ->assertOk()
        ->assertJson([
            'contact_usage_count' => 1,
            'usage_count' => 1,
        ]);
});

test('标签使用情况忽略已删除联系人', function () {
    $tag = Tag::factory()->create([
    ]);

    $contact = Contact::factory()->create([
    ]);

    DB::table('contact_tag_assignments')->insert([
        'tag_id' => $tag->id,
        'contact_id' => $contact->id,
        'source' => 'manual',
        'created_at' => now(),
    ]);

    $contact->delete();

    $this->actingAs($this->user)
        ->getJson(route('app.manage.tags.usage', ['id' => $tag->id]))
        ->assertOk()
        ->assertJson([
            'contact_usage_count' => 0,
            'usage_count' => 0,
        ]);
});

test('重命名标签刷新关联联系人搜索索引', function () {
    $tag = Tag::factory()->create([
        'name' => 'VIP',
    ]);

    $contact = Contact::factory()->create([
    ]);

    DB::table('contact_tag_assignments')->insert([
        'tag_id' => $tag->id,
        'contact_id' => $contact->id,
        'source' => 'manual',
        'created_at' => now(),
    ]);

    $contact->searchable();

    $this->actingAs($this->user)
        ->put(route('app.manage.tags.update', ['id' => $tag->id]), [
            'tag_group_id' => $tag->tag_group_id,
            'name' => 'Priority',
            'color' => $tag->color,
            'description' => $tag->description,
        ])
        ->assertRedirect();

    $searchResults = collect(
        Contact::search('Priority')

            ->keys()
    );

    expect($searchResults)->toContain($contact->id);
});
