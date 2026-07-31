<?php

use App\Actions\Conversation\GetContactConversationTagAggregatesAction;
use App\Enums\TagSource;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Tag;
use App\Models\TagGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
    $this->contact = Contact::factory()->create([]);
    $this->conversation = Conversation::factory()->create([
        'contact_id' => $this->contact->id,
    ]);
    $this->conversationGroup = TagGroup::factory()->conversation()->create([]);
    $this->contactGroup = TagGroup::factory()->contact()->create([]);
});

test('人工可以给会话附加会话维度标签', function () {
    $tag = Tag::factory()->forGroup($this->conversationGroup)->create(['name' => '退款']);

    $this->actingAs($this->user)
        ->postJson(route('app.inbox.conversations.tags.attach', [
            'conversation' => $this->conversation->id,
        ]), ['tag_id' => $tag->id])
        ->assertOk();

    $row = DB::table('conversation_tag_assignments')
        ->where('conversation_id', $this->conversation->id)
        ->where('tag_id', $tag->id)
        ->first();

    expect($row)->not->toBeNull();
    expect($row->source)->toBe('manual');
    expect($row->assigned_by_user_id)->toBe($this->user->id);
    expect($row->removed_at)->toBeNull();
});

test('不能给会话打联系人维度标签', function () {
    $tag = Tag::factory()->forGroup($this->contactGroup)->create(['name' => 'VIP']);

    $this->actingAs($this->user)
        ->postJson(route('app.inbox.conversations.tags.attach', [
            'conversation' => $this->conversation->id,
        ]), ['tag_id' => $tag->id])
        ->assertStatus(422);

    expect(DB::table('conversation_tag_assignments')->count())->toBe(0);
});

test('人工移除会话标签写入抑制墓碑而非物理删除', function () {
    $tag = Tag::factory()->forGroup($this->conversationGroup)->create(['name' => '退款']);

    DB::table('conversation_tag_assignments')->insert([
        'conversation_id' => $this->conversation->id,
        'tag_id' => $tag->id,
        'source' => TagSource::Ai->value,
        'confidence' => 0.9,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->deleteJson(route('app.inbox.conversations.tags.detach', [
            'conversation' => $this->conversation->id,
            'tagId' => $tag->id,
        ]))
        ->assertOk();

    $row = DB::table('conversation_tag_assignments')
        ->where('conversation_id', $this->conversation->id)
        ->where('tag_id', $tag->id)
        ->first();

    // 行仍在，但被置为抑制墓碑，且不再出现在有效标签关系里。
    expect($row)->not->toBeNull();
    expect($row->removed_at)->not->toBeNull();
    expect($row->removed_by_user_id)->toBe($this->user->id);
    expect($this->conversation->fresh()->tags()->count())->toBe(0);
});

test('重新人工附加被抑制的标签会复活为人工来源', function () {
    $tag = Tag::factory()->forGroup($this->conversationGroup)->create(['name' => '退款']);

    DB::table('conversation_tag_assignments')->insert([
        'conversation_id' => $this->conversation->id,
        'tag_id' => $tag->id,
        'source' => TagSource::Ai->value,
        'removed_at' => now(),
        'removed_by_user_id' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->postJson(route('app.inbox.conversations.tags.attach', [
            'conversation' => $this->conversation->id,
        ]), ['tag_id' => $tag->id])
        ->assertOk();

    $row = DB::table('conversation_tag_assignments')
        ->where('conversation_id', $this->conversation->id)
        ->where('tag_id', $tag->id)
        ->first();

    expect($row->removed_at)->toBeNull();
    expect($row->source)->toBe('manual');
});

test('联系人咨询概况按标签聚合计数且忽略被抑制标签', function () {
    $refund = Tag::factory()->forGroup($this->conversationGroup)->create(['name' => '退款']);
    $tech = Tag::factory()->forGroup($this->conversationGroup)->create(['name' => '技术支持']);

    $secondConversation = Conversation::factory()->create([
        'contact_id' => $this->contact->id,
    ]);

    $now = now();
    DB::table('conversation_tag_assignments')->insert([
        ['conversation_id' => $this->conversation->id, 'tag_id' => $refund->id, 'source' => 'ai', 'removed_at' => null, 'created_at' => $now, 'updated_at' => $now],
        ['conversation_id' => $secondConversation->id, 'tag_id' => $refund->id, 'source' => 'ai', 'removed_at' => null, 'created_at' => $now, 'updated_at' => $now],
        ['conversation_id' => $this->conversation->id, 'tag_id' => $tech->id, 'source' => 'ai', 'removed_at' => null, 'created_at' => $now, 'updated_at' => $now],
        // 被抑制的不计入
        ['conversation_id' => $secondConversation->id, 'tag_id' => $tech->id, 'source' => 'ai', 'removed_at' => $now, 'created_at' => $now, 'updated_at' => $now],
    ]);

    $aggregates = GetContactConversationTagAggregatesAction::run($this->contact->id);

    expect($aggregates)->toHaveCount(2);
    expect($aggregates[0]->name)->toBe('退款');
    expect($aggregates[0]->count)->toBe(2);
    expect($aggregates[1]->name)->toBe('技术支持');
    expect($aggregates[1]->count)->toBe(1);
});
