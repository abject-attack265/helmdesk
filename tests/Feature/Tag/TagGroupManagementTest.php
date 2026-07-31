<?php

use App\Models\Tag;
use App\Models\TagGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
});

test('可以创建标签组并指定适用维度', function () {
    $this->actingAs($this->user)
        ->from(route('app.manage.tags.index', []))
        ->post(route('app.manage.tags.groups.store', []), [
            'name' => '  咨询意图  ',
            'scope' => 'conversation',
        ])
        ->assertRedirect();

    $group = TagGroup::query()->first();
    expect($group)->not()->toBeNull();
    expect($group->name)->toBe('咨询意图');
    expect($group->normalized_name)->toBe('咨询意图');
    expect($group->scope->value)->toBe('conversation');
    expect($group->created_by_user_id)->toBe($this->user->id);
});

test('不能创建重复名称的标签组', function () {
    TagGroup::factory()->create([
        'name' => '咨询意图',
    ]);

    $this->actingAs($this->user)
        ->post(route('app.manage.tags.groups.store', []), [
            'name' => '咨询意图',
            'scope' => 'conversation',
        ])
        ->assertSessionHasErrors(['name']);
});

test('创建标签组拒绝非法维度', function () {
    $this->actingAs($this->user)
        ->post(route('app.manage.tags.groups.store', []), [
            'name' => '非法维度组',
            'scope' => 'invalid_scope',
        ])
        ->assertSessionHasErrors(['scope']);
});

test('可以重命名标签组', function () {
    $group = TagGroup::factory()->create([
        'name' => '旧组名',
    ]);

    $this->actingAs($this->user)
        ->put(route('app.manage.tags.groups.update', ['id' => $group->id]), [
            'name' => '新组名',
        ])
        ->assertRedirect();

    expect($group->fresh()->name)->toBe('新组名');
});

test('空标签组可以删除', function () {
    $group = TagGroup::factory()->create([
    ]);

    $this->actingAs($this->user)
        ->delete(route('app.manage.tags.groups.destroy', ['id' => $group->id]))
        ->assertRedirect();

    $this->assertSoftDeleted('tag_groups', ['id' => $group->id]);
});

test('非空标签组不能删除', function () {
    $group = TagGroup::factory()->create([
    ]);
    Tag::factory()->forGroup($group)->create();

    $this->actingAs($this->user)
        ->delete(route('app.manage.tags.groups.destroy', ['id' => $group->id]))
        ->assertUnprocessable();

    expect(TagGroup::query()->find($group->id))->not()->toBeNull();
});

test('恢复标签时连带恢复已被删除的标签组', function () {
    $group = TagGroup::factory()->contact()->create([]);
    $tag = Tag::factory()->forGroup($group)->create(['name' => 'VIP']);

    // 先删标签，再删空组（删组时组内已无未删除标签，允许删除）。
    $tag->delete();
    $group->delete();

    $this->actingAs($this->user)
        ->put(route('app.manage.tags.restore', ['id' => $tag->id]))
        ->assertRedirect();

    expect($tag->fresh()->deleted_at)->toBeNull();
    // 组也被一并恢复，恢复出来的标签才不会因组不可见而消失。
    expect(TagGroup::query()->find($group->id))->not->toBeNull();
});

test('标签只能在同维度的组之间移动', function () {
    $conversationGroup = TagGroup::factory()->conversation()->create([]);
    $contactGroup = TagGroup::factory()->contact()->create([]);
    $tag = Tag::factory()->forGroup($conversationGroup)->create(['name' => '退款']);

    $this->actingAs($this->user)
        ->put(route('app.manage.tags.update', ['id' => $tag->id]), [
            'tag_group_id' => $contactGroup->id,
            'name' => '退款',
        ])
        ->assertSessionHasErrors(['tag_group_id']);

    expect($tag->fresh()->tag_group_id)->toBe($conversationGroup->id);
});
