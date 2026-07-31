<?php

use App\Enums\ConversationStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Enums\UserOnlineStatus;
use App\Events\Reception\InstanceReceptionUpdated;
use App\Events\Reception\VisitorConversationUpdated;
use App\Jobs\Reception\FlushReceptionBufferJob;
use App\Models\Attachment;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\ConversationThread;
use App\Models\Membership;
use App\Models\ReceptionPlan;
use App\Models\ReceptionPlanVersion;
use App\Models\User;
use App\Services\Reception\ReceptionDebouncer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::store()->flush();
});

/**
 * 建一个绑定已发布版本与有效 AI 模型的访客渠道。
 */
function visitorChatChannel(): Channel
{
    $app = createSystemSettings();

    // 系统模型池中有可用接待对话模型即可让渠道判定为可用。
    makeAiModel();

    $plan = ReceptionPlan::factory()->create();
    $version = ReceptionPlanVersion::factory()->for($plan, 'plan')->create();

    $channel = Channel::factory()->create([
        'reception_plan_id' => $version->reception_plan_id,
    ]);
    $agent = User::factory()->create();
    Membership::query()->create([
        'user_id' => $agent->id,
        'online_status' => UserOnlineStatus::Online->value,
    ]);

    return $channel;
}

test('访客发消息落库、接入 turn 管道并写回会话 cookie', function () {
    Bus::fake();
    $channel = visitorChatChannel();

    $response = $this->postJson("/api/chat/{$channel->code}/messages", [
        'content' => '你好，我要查订单',
    ]);

    $response->assertOk()
        ->assertJsonStructure(['session_token', 'conversation_id', 'messages', 'agent_activity'])
        ->assertJsonMissing(['inbox_status']);

    $conversationId = $response->json('conversation_id');
    expect($response->json('agent_activity.active'))->toBeTrue();

    // 消息已落库
    expect(ConversationMessage::query()
        ->where('conversation_id', $conversationId)
        ->where('role', MessageRole::Visitor)
        ->where('content', '你好，我要查订单')
        ->exists())->toBeTrue();

    // 接入了 turn 管道
    Bus::assertDispatched(FlushReceptionBufferJob::class, fn (FlushReceptionBufferJob $j): bool => $j->conversationId === $conversationId);

    // 写回了会话 cookie
    $response->assertCookie("helmdesk_visitor_{$channel->code}");
});

test('访客发消息仍向两端广播会话变更（已 defer 到响应下发后，信号不丢）', function () {
    Bus::fake();
    Event::fake([VisitorConversationUpdated::class, InstanceReceptionUpdated::class]);
    $channel = visitorChatChannel();

    $response = $this->postJson("/api/chat/{$channel->code}/messages", [
        'content' => '你好，我要查订单',
    ])->assertOk();

    $conversationId = $response->json('conversation_id');

    // 同步广播被 defer 到响应之后执行，但接收端的两条信号都不能丢：
    // 坐席收件箱（应用私有频道）与访客其它标签页（会话公开频道）。
    Event::assertDispatched(
        InstanceReceptionUpdated::class,
        fn (InstanceReceptionUpdated $e): bool => ($e->payload['conversation_id'] ?? null) === $conversationId
            && ($e->payload['event'] ?? null) === 'visitor_message_created',
    );
    Event::assertDispatched(
        VisitorConversationUpdated::class,
        fn (VisitorConversationUpdated $e): bool => $e->conversationId === $conversationId,
    );
});

test('附件-only 消息落库但不唤起 AI turn', function () {
    Bus::fake();
    fakeAttachmentStorage();
    $channel = visitorChatChannel();

    // 状态读取只签发访客令牌。
    $state = $this->getJson("/api/chat/{$channel->code}/state")->assertOk();
    $sessionToken = $state->json('session_token');

    $attachment = Attachment::factory()->create([
        'session_token_hash' => hash('sha256', $sessionToken),
    ]);
    $attachment->filesystem()->put($attachment->object_key, 'fake-file');

    $response = $this->postJson("/api/chat/{$channel->code}/messages", [
        'attachment_ids' => [(string) $attachment->id],
    ], [
        'X-Helmdesk-Visitor-Token' => $sessionToken,
    ])->assertOk();
    $conversationId = $response->json('conversation_id');

    // 附件消息已落库
    expect($conversationId)->not->toBeNull()
        ->and(ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('role', MessageRole::Visitor)
            ->where('kind', MessageKind::File)
            ->exists())->toBeTrue();

    // 但没有可回复文本，不接入 turn 管道（与 Telegram 纯媒体行为对齐）
    Bus::assertNotDispatched(FlushReceptionBufferJob::class);
});

test('空消息返回 422', function () {
    Bus::fake();
    $channel = visitorChatChannel();

    $this->postJson("/api/chat/{$channel->code}/messages", ['content' => '   '])
        ->assertStatus(422);

    Bus::assertNotDispatched(FlushReceptionBufferJob::class);
});

test('首条消息引用无效附件时回滚联系人、会话与线程', function () {
    Bus::fake();
    $channel = visitorChatChannel();
    $state = $this->getJson("/api/chat/{$channel->code}/state")->assertOk();

    $this->postJson("/api/chat/{$channel->code}/messages", [
        'attachment_ids' => ['01J00000000000000000000000'],
    ], [
        'X-Helmdesk-Visitor-Token' => $state->json('session_token'),
    ])->assertUnprocessable();

    expect(Contact::query()->count())->toBe(0)
        ->and(Conversation::query()->count())->toBe(0)
        ->and(ConversationThread::query()->count())->toBe(0);
    Bus::assertNotDispatched(FlushReceptionBufferJob::class);
});

test('state 端点返回空快照且不创建接待资源', function () {
    $channel = visitorChatChannel();

    $this->getJson("/api/chat/{$channel->code}/state")
        ->assertOk()
        ->assertJsonPath('conversation_id', null)
        ->assertJsonPath('status', null)
        ->assertJsonPath('messages', [])
        ->assertJsonStructure(['session_token'])
        ->assertJsonMissing(['inbox_status']);

    expect(Contact::query()->count())->toBe(0)
        ->and(Conversation::query()->count())->toBe(0)
        ->and(ConversationThread::query()->count())->toBe(0);
});

test('暂停渠道只允许读取仍开放的已有会话', function () {
    Bus::fake();
    $channel = visitorChatChannel();

    $message = $this->postJson("/api/chat/{$channel->code}/messages", [
        'content' => '已有开放会话',
    ])->assertOk();
    $sessionToken = $message->json('session_token');
    $conversationId = $message->json('conversation_id');
    $channel->delete();

    $this->getJson("/api/chat/{$channel->code}/state", [
        'X-Helmdesk-Visitor-Token' => $sessionToken,
    ])->assertOk()
        ->assertJsonPath('conversation_id', $conversationId);

    Conversation::query()->whereKey($conversationId)->update([
        'status' => ConversationStatus::Closed,
        'closed_at' => now(),
    ]);

    $this->getJson("/api/chat/{$channel->code}/state", [
        'X-Helmdesk-Visitor-Token' => $sessionToken,
    ])->assertGone();
});

test('空状态撤回消息返回 404 且不创建接待资源', function () {
    $channel = visitorChatChannel();

    $this->postJson("/api/chat/{$channel->code}/messages/01J00000000000000000000000/recall")
        ->assertNotFound();

    expect(Contact::query()->count())->toBe(0)
        ->and(Conversation::query()->count())->toBe(0)
        ->and(ConversationThread::query()->count())->toBe(0);
});

test('已关闭会话撤回消息返回 422 且不创建新会话', function () {
    Bus::fake();
    $channel = visitorChatChannel();

    $state = $this->postJson("/api/chat/{$channel->code}/messages", [
        'content' => '准备撤回',
    ])->assertOk();
    $conversationId = $state->json('conversation_id');
    $messageId = ConversationMessage::query()
        ->where('conversation_id', $conversationId)
        ->where('role', MessageRole::Visitor)
        ->value('id');

    Conversation::query()->whereKey($conversationId)->update([
        'status' => ConversationStatus::Closed,
        'closed_at' => now(),
    ]);

    $this->postJson("/api/chat/{$channel->code}/messages/{$messageId}/recall", [], [
        'X-Helmdesk-Visitor-Token' => $state->json('session_token'),
    ])->assertUnprocessable();

    expect(Conversation::query()->count())->toBe(1);
});

test('typing 端点对已存在的 AI 接待会话推迟 flush', function () {
    $channel = visitorChatChannel();

    $message = $this->postJson("/api/chat/{$channel->code}/messages", [
        'content' => '我还在输入',
    ])->assertOk();
    $sessionToken = $message->json('session_token');
    $conversationId = $message->json('conversation_id');

    $debouncer = app(ReceptionDebouncer::class);
    expect($debouncer->typingHoldRemainingMs($conversationId))->toBe(0);

    $this->postJson("/api/chat/{$channel->code}/typing", [], [
        'X-Helmdesk-Visitor-Token' => $sessionToken,
    ])->assertNoContent();

    expect($debouncer->typingHoldRemainingMs($conversationId))->toBeGreaterThan(0);
});

test('typing 端点对未知会话静默返回 204', function () {
    $channel = visitorChatChannel();

    $this->postJson("/api/chat/{$channel->code}/typing", [], [
        'X-Helmdesk-Visitor-Token' => str_repeat('a', 32),
    ])->assertNoContent();
});
