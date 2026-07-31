<?php

use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
    $this->contact = Contact::factory()->create([
    ]);
    $this->tag = Tag::factory()->create([
    ]);
});

test('可以附加标签到联系人', function () {
    $this->actingAs($this->user)
        ->postJson(route('app.contacts.tags.attach', [
            'id' => $this->contact->id,
        ]), [
            'tag_id' => $this->tag->id,
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(
        DB::table('contact_tag_assignments')
            ->where('tag_id', $this->tag->id)
            ->where('contact_id', $this->contact->id)
            ->exists()
    )->toBeTrue();
});

test('附加同一标签是幂等的', function () {
    DB::table('contact_tag_assignments')->insert([
        'tag_id' => $this->tag->id,
        'contact_id' => $this->contact->id,
        'source' => 'manual',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->postJson(route('app.contacts.tags.attach', [
            'id' => $this->contact->id,
        ]), [
            'tag_id' => $this->tag->id,
        ])
        ->assertOk();

    expect(
        DB::table('contact_tag_assignments')
            ->where('tag_id', $this->tag->id)
            ->where('contact_id', $this->contact->id)
            ->count()
    )->toBe(1);
});

test('可以分离标签来自联系人', function () {
    DB::table('contact_tag_assignments')->insert([
        'tag_id' => $this->tag->id,
        'contact_id' => $this->contact->id,
        'source' => 'manual',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->deleteJson(route('app.contacts.tags.detach', [
            'id' => $this->contact->id,
            'tagId' => $this->tag->id,
        ]))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(
        DB::table('contact_tag_assignments')
            ->where('tag_id', $this->tag->id)
            ->where('contact_id', $this->contact->id)
            ->exists()
    )->toBeFalse();
});

test('附加创建活动日志', function () {
    $this->actingAs($this->user)
        ->postJson(route('app.contacts.tags.attach', [
            'id' => $this->contact->id,
        ]), [
            'tag_id' => $this->tag->id,
        ])
        ->assertOk();

    expect(
        DB::table('contact_activity_logs')
            ->where('contact_id', $this->contact->id)
            ->where('action', 'tag_attached')
            ->exists()
    )->toBeTrue();
});

test('联系人详情包含标签活动日志载荷', function () {
    $this->actingAs($this->user)
        ->postJson(route('app.contacts.tags.attach', [
            'id' => $this->contact->id,
        ]), [
            'tag_id' => $this->tag->id,
        ])
        ->assertOk();

    $this->actingAs($this->user)
        ->getJson(route('app.contacts.show', [
            'id' => $this->contact->id,
        ]))
        ->assertOk()
        ->assertJsonPath('activity_logs.0.action', 'tag_attached')
        ->assertJsonPath('activity_logs.0.payload.tag_name', $this->tag->name);
});

test('分离创建活动日志', function () {
    DB::table('contact_tag_assignments')->insert([
        'tag_id' => $this->tag->id,
        'contact_id' => $this->contact->id,
        'source' => 'manual',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->deleteJson(route('app.contacts.tags.detach', [
            'id' => $this->contact->id,
            'tagId' => $this->tag->id,
        ]))
        ->assertOk();

    expect(
        DB::table('contact_activity_logs')
            ->where('contact_id', $this->contact->id)
            ->where('action', 'tag_detached')
            ->exists()
    )->toBeTrue();
});

test('分离标签是未附加是无操作', function () {
    $this->actingAs($this->user)
        ->deleteJson(route('app.contacts.tags.detach', [
            'id' => $this->contact->id,
            'tagId' => $this->tag->id,
        ]))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(
        DB::table('contact_activity_logs')
            ->where('contact_id', $this->contact->id)
            ->where('action', 'tag_detached')
            ->exists()
    )->toBeFalse();
});

test('联系人详情包含标签', function () {
    DB::table('contact_tag_assignments')->insert([
        'tag_id' => $this->tag->id,
        'contact_id' => $this->contact->id,
        'source' => 'manual',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->getJson(route('app.contacts.show', [
            'id' => $this->contact->id,
        ]))
        ->assertOk()
        ->assertJsonPath('tags.0.id', $this->tag->id);
});

test('联系人列表包含联系人标签', function () {
    DB::table('contact_tag_assignments')->insert([
        'tag_id' => $this->tag->id,
        'contact_id' => $this->contact->id,
        'source' => 'manual',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->get(route('app.contacts.index', [
            'type' => 'all',
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('contacts/Index')
            ->where('contact_list.0.id', $this->contact->id)
            ->where('contact_list.0.tags.0.id', $this->tag->id)
            ->where('contact_list.0.tags.0.name', $this->tag->name)
            ->etc()
        );
});

test('联系人合并转移标签', function () {
    $contact2 = Contact::factory()->create([
    ]);

    $tag2 = Tag::factory()->create([
    ]);

    DB::table('contact_tag_assignments')->insert([
        'tag_id' => $this->tag->id,
        'contact_id' => $this->contact->id,
        'source' => 'manual',
        'created_at' => now(),
    ]);
    DB::table('contact_tag_assignments')->insert([
        'tag_id' => $tag2->id,
        'contact_id' => $contact2->id,
        'source' => 'manual',
        'created_at' => now(),
    ]);
    DB::table('contact_tag_assignments')->insert([
        'tag_id' => $this->tag->id,
        'contact_id' => $contact2->id,
        'source' => 'manual',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->post(route('app.contacts.merge', []), [
            'target_contact_id' => $this->contact->id,
            'merged_contact_id' => $contact2->id,
        ])
        ->assertRedirect();

    $targetTags = DB::table('contact_tag_assignments')
        ->where('contact_id', $this->contact->id)
        ->pluck('tag_id')
        ->all();

    expect($targetTags)->toContain($this->tag->id);
    expect($targetTags)->toContain($tag2->id);
    expect(DB::table('contact_tag_assignments')
        ->where('contact_id', $this->contact->id)
        ->where('tag_id', $this->tag->id)
        ->count())->toBe(1);
    expect(DB::table('contact_tag_assignments')->where('contact_id', $contact2->id)->exists())->toBeFalse();
});

test('联系人合并不会转移软已删除标签', function () {
    $contact2 = Contact::factory()->create([
    ]);

    $deletedTag = Tag::factory()->create([
    ]);
    $deletedTag->delete();

    DB::table('contact_tag_assignments')->insert([
        'tag_id' => $deletedTag->id,
        'contact_id' => $contact2->id,
        'source' => 'manual',
        'created_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->post(route('app.contacts.merge', []), [
            'target_contact_id' => $this->contact->id,
            'merged_contact_id' => $contact2->id,
        ])
        ->assertRedirect();

    expect(DB::table('contact_tag_assignments')
        ->where('contact_id', $this->contact->id)
        ->where('tag_id', $deletedTag->id)
        ->exists())->toBeFalse()
        ->and(DB::table('contact_tag_assignments')
            ->where('contact_id', $contact2->id)
            ->where('tag_id', $deletedTag->id)
            ->exists())->toBeFalse();
});
