<?php

use App\Actions\Reception\SubmitConversationRatingAction;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationRatingHandledBy;
use App\Enums\ConversationRatingScore;
use App\Enums\ConversationStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Exceptions\BusinessException;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationRating;
use App\Models\User;
use App\Services\Reception\ReceptionStateBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 造一个已关闭的访客会话（默认纯 AI 处理）。
 */
function closedConversation(array $overrides = []): Conversation
{
    $app = createSystemSettings();
    $contact = Contact::factory()->visitor()->create([]);
    $channel = Channel::factory()->create([]);

    return Conversation::factory()->forContact($contact)->create(array_merge([
        'channel_id' => $channel->id,
        'status' => ConversationStatus::Closed,
        // 评价资格依赖关单时的接待状态（排队中不可评价），固定为 AI 接待避免工厂随机值干扰。
        'inbox_status' => ConversationInboxStatus::AiHandling,
        'closed_at' => now(),
    ], $overrides));
}

test('提交评价落库、写时间线事件并归因为 AI', function () {
    $conversation = closedConversation();

    $rating = SubmitConversationRatingAction::run($conversation, ConversationRatingScore::Positive, '很满意');

    expect($rating->score)->toBe(ConversationRatingScore::Positive)
        ->and($rating->handled_by)->toBe(ConversationRatingHandledBy::Ai)
        ->and($rating->comment)->toBe('很满意');

    $this->assertDatabaseHas('conversation_ratings', [
        'conversation_id' => $conversation->id,
        'score' => 'positive',
        'handled_by' => 'ai',
        'channel_type' => 'web',
    ]);
    $this->assertDatabaseHas('conversation_events', [
        'conversation_id' => $conversation->id,
        'type' => 'feedback_received',
    ]);
});

test('有指派坐席时归因为人工并快照坐席', function () {
    $agent = User::factory()->create();
    $conversation = closedConversation(['assigned_user_id' => $agent->id]);

    $rating = SubmitConversationRatingAction::run($conversation, ConversationRatingScore::Negative);

    expect($rating->handled_by)->toBe(ConversationRatingHandledBy::Human)
        ->and($rating->assigned_user_id)->toBe($agent->id);
});

test('出现过 teammate 消息时即便无指派也归因为人工', function () {
    $conversation = closedConversation();
    ConversationMessage::factory()->forConversation($conversation)->create([
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => '您好，我来帮您',
        'sender_user_id' => User::factory()->create()->id,
    ]);

    $rating = SubmitConversationRatingAction::run($conversation, ConversationRatingScore::Positive);

    expect($rating->handled_by)->toBe(ConversationRatingHandledBy::Human);
});

test('未关闭也未被邀请的会话不能评价', function () {
    $conversation = closedConversation(['status' => ConversationStatus::Open, 'closed_at' => null]);

    expect(fn () => SubmitConversationRatingAction::run($conversation, ConversationRatingScore::Positive))
        ->toThrow(BusinessException::class);
});

test('排队中无人接待就被关掉的会话不可评价', function () {
    $conversation = closedConversation(['inbox_status' => ConversationInboxStatus::TeammatePending]);

    expect(fn () => SubmitConversationRatingAction::run($conversation, ConversationRatingScore::Positive))
        ->toThrow(BusinessException::class);
});

test('已关闭的 AI 会话 can_rate 为真，排队中被关掉的会话 can_rate 为假', function () {
    $closedAi = closedConversation();
    $channel = Channel::query()->find($closedAi->channel_id);
    $state = ReceptionStateBuilder::build($channel, $closedAi->fresh(), 'tok');

    expect($state->can_rate)->toBeTrue()
        ->and($state->status)->toBe('closed')
        ->and($state->rating)->toBeNull();

    $closedPending = closedConversation(['inbox_status' => ConversationInboxStatus::TeammatePending]);
    $pendingChannel = Channel::query()->find($closedPending->channel_id);
    $pendingState = ReceptionStateBuilder::build($pendingChannel, $closedPending->fresh(), 'tok');

    expect($pendingState->can_rate)->toBeFalse();
});

test('重复评价覆盖更新且只保留一条', function () {
    $conversation = closedConversation();

    SubmitConversationRatingAction::run($conversation, ConversationRatingScore::Positive, '一开始满意');
    SubmitConversationRatingAction::run($conversation, ConversationRatingScore::Negative, '改主意了');

    $ratings = ConversationRating::query()->where('conversation_id', $conversation->id)->get();
    expect($ratings)->toHaveCount(1)
        ->and($ratings->first()->score)->toBe(ConversationRatingScore::Negative)
        ->and($ratings->first()->comment)->toBe('改主意了');
});

test('访客 state 已关闭未评价时 can_rate 为真，评价后回填 rating', function () {
    $conversation = closedConversation();
    $channel = Channel::query()->find($conversation->channel_id);

    $before = ReceptionStateBuilder::build($channel, $conversation->fresh(), 'tok');
    expect($before->can_rate)->toBeTrue()
        ->and($before->rating)->toBeNull();

    SubmitConversationRatingAction::run($conversation, ConversationRatingScore::Positive, 'good');

    $after = ReceptionStateBuilder::build($channel, $conversation->fresh(), 'tok');
    expect($after->can_rate)->toBeFalse()
        ->and($after->rating?->score)->toBe(ConversationRatingScore::Positive);
});

test('访客端点按会话 token 提交评价', function () {
    $app = createSystemSettings();
    $channel = Channel::factory()->create([]);
    $contact = Contact::factory()->visitor()->create([]);
    $token = str_repeat('a', 32);
    ContactIdentity::factory()->session($token)->create([
        'contact_id' => $contact->id,
    ]);
    $conversation = Conversation::factory()->forContact($contact)->create([
        'channel_id' => $channel->id,
        'status' => ConversationStatus::Closed,
        'inbox_status' => ConversationInboxStatus::AiHandling,
        'closed_at' => now(),
    ]);

    $this->postJson("/api/chat/{$channel->code}/rating", [
        'score' => 'positive',
        'comment' => '解决得很快',
    ], ['X-Helmdesk-Visitor-Token' => $token])
        ->assertSuccessful()
        ->assertJsonPath('can_rate', false)
        ->assertJsonPath('rating.score', 'positive');

    $this->assertDatabaseHas('conversation_ratings', [
        'conversation_id' => $conversation->id,
        'score' => 'positive',
        'comment' => '解决得很快',
        'channel_type' => 'web',
    ]);
});

test('访客端点对排队中被关掉的会话拒绝评价', function () {
    $app = createSystemSettings();
    $channel = Channel::factory()->create([]);
    $contact = Contact::factory()->visitor()->create([]);
    $token = str_repeat('c', 32);
    ContactIdentity::factory()->session($token)->create([
        'contact_id' => $contact->id,
    ]);
    Conversation::factory()->forContact($contact)->create([
        'channel_id' => $channel->id,
        'status' => ConversationStatus::Closed,
        'inbox_status' => ConversationInboxStatus::TeammatePending,
        'closed_at' => now(),
    ]);

    $this->postJson("/api/chat/{$channel->code}/rating", [
        'score' => 'negative',
        'comment' => '没解决',
    ], ['X-Helmdesk-Visitor-Token' => $token])->assertStatus(422);
});

test('访客端点对未关闭会话返回 422', function () {
    $app = createSystemSettings();
    $channel = Channel::factory()->create([]);
    $contact = Contact::factory()->visitor()->create([]);
    $token = str_repeat('b', 32);
    ContactIdentity::factory()->session($token)->create([
        'contact_id' => $contact->id,
    ]);
    Conversation::factory()->forContact($contact)->create([
        'channel_id' => $channel->id,
        'status' => ConversationStatus::Open,
    ]);

    $this->postJson("/api/chat/{$channel->code}/rating", [
        'score' => 'positive',
    ], ['X-Helmdesk-Visitor-Token' => $token])->assertStatus(422);
});
