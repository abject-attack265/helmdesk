<?php

use App\Actions\Conversation\ApplyConversationTagSuggestionsAction;
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
    $this->group = TagGroup::factory()->conversation()->create([]);
});

function makeConversationTag(string $name): Tag
{
    return Tag::factory()->forGroup(test()->group)->create(['name' => $name]);
}

function suggestion(Tag $tag, float $confidence = 0.9, ?string $reason = '依据'): array
{
    return ['tag_id' => $tag->id, 'confidence' => $confidence, 'reason' => $reason, 'based_on_seq_no' => 10];
}

test('应用建议会新建 AI 标签并带置信度与依据', function () {
    $tag = makeConversationTag('退款');

    ApplyConversationTagSuggestionsAction::run($this->conversation, [suggestion($tag, 0.92, '客户要退款')]);

    $row = DB::table('conversation_tag_assignments')
        ->where('conversation_id', $this->conversation->id)
        ->where('tag_id', $tag->id)
        ->first();

    expect($row->source)->toBe('ai');
    expect((float) $row->confidence)->toBe(0.92);
    expect($row->reason)->toBe('客户要退款');
    expect((int) $row->based_on_seq_no)->toBe(10);
});

test('进行中只增不撤：不再建议的 AI 标签保留', function () {
    $kept = makeConversationTag('退款');
    $stale = makeConversationTag('技术支持');

    ApplyConversationTagSuggestionsAction::run($this->conversation, [suggestion($kept), suggestion($stale)]);
    ApplyConversationTagSuggestionsAction::run($this->conversation, [suggestion($kept)]);

    expect($this->conversation->tags()->pluck('tags.id')->all())
        ->toContain($kept->id)
        ->toContain($stale->id);
});

test('尊重人工抑制：被移除的标签不会被 AI 复打', function () {
    $tag = makeConversationTag('退款');

    DB::table('conversation_tag_assignments')->insert([
        'conversation_id' => $this->conversation->id,
        'tag_id' => $tag->id,
        'source' => TagSource::Ai->value,
        'removed_at' => now(),
        'removed_by_user_id' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    ApplyConversationTagSuggestionsAction::run($this->conversation, [suggestion($tag)]);

    $row = DB::table('conversation_tag_assignments')
        ->where('conversation_id', $this->conversation->id)
        ->where('tag_id', $tag->id)
        ->first();

    // 仍是抑制墓碑，没被复活
    expect($row->removed_at)->not->toBeNull();
    expect($this->conversation->tags()->count())->toBe(0);
});

test('不覆盖人工标签：AI 建议命中人工标签时保持人工来源', function () {
    $tag = makeConversationTag('退款');

    DB::table('conversation_tag_assignments')->insert([
        'conversation_id' => $this->conversation->id,
        'tag_id' => $tag->id,
        'source' => TagSource::Manual->value,
        'assigned_by_user_id' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    ApplyConversationTagSuggestionsAction::run($this->conversation, [suggestion($tag, 0.99)]);

    $row = DB::table('conversation_tag_assignments')
        ->where('conversation_id', $this->conversation->id)
        ->where('tag_id', $tag->id)
        ->first();

    expect($row->source)->toBe('manual');
    expect($row->confidence)->toBeNull();
});

test('定稿不删除人工标签即便它不在建议里', function () {
    $manual = makeConversationTag('VIP相关');
    $aiTag = makeConversationTag('退款');

    DB::table('conversation_tag_assignments')->insert([
        'conversation_id' => $this->conversation->id,
        'tag_id' => $manual->id,
        'source' => TagSource::Manual->value,
        'assigned_by_user_id' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    ApplyConversationTagSuggestionsAction::run($this->conversation, [suggestion($aiTag)]);

    expect($this->conversation->tags()->pluck('tags.id')->all())
        ->toContain($manual->id)
        ->toContain($aiTag->id);
});
