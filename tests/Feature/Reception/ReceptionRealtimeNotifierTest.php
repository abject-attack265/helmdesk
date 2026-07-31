<?php

use App\Events\Reception\InstanceReceptionUpdated;
use App\Events\Reception\ReceptionAgentActivityUpdated;
use App\Events\Reception\VisitorConversationUpdated;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationThread;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/**
 * 创建带联系人、渠道和稳定线程的接待会话。
 */
function createRealtimeThreadedConversation(): Conversation
{
    $contact = Contact::factory()->create();
    $channel = Channel::factory()->create();

    return Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->create()
        ->refresh();
}

test('它向接待收件箱和访客会话主题各发一条变更信号', function () {
    Event::fake([InstanceReceptionUpdated::class, VisitorConversationUpdated::class]);

    $conversation = createRealtimeThreadedConversation();

    app(ReceptionRealtimeNotifier::class)->conversationChanged($conversation, 'visitor_message_created', ['preview_meta' => 'x']);

    // 接待收件箱主题携带会话元信息和 meta。
    Event::assertDispatched(InstanceReceptionUpdated::class, function (InstanceReceptionUpdated $event) use ($conversation): bool {
        $payload = $event->payload;

        return $event->broadcastOn()->name === 'private-urn:helmdesk:reception:inbox'
            && $event->broadcastAs() === 'reception'
            && $payload['event'] === 'visitor_message_created'
            && $payload['thread_id'] === (string) ConversationThread::requireForConversation($conversation)->id
            && $payload['conversation_id'] === (string) $conversation->id
            && $payload['inbox_status'] === $conversation->inbox_status->value
            && $payload['last_message_preview'] === $conversation->last_message_preview
            && $payload['preview_meta'] === 'x';
    });

    // 会话主题只发送变更信号，访客端收到后回源拉取最新会话。
    Event::assertDispatched(VisitorConversationUpdated::class, function (VisitorConversationUpdated $event) use ($conversation): bool {
        $payload = $event->payload;
        $keys = collect($payload)->keys()->sort()->values()->all();

        return $event->conversationId === (string) $conversation->id
            && $event->broadcastOn()->name === 'urn:helmdesk:reception:conversation:'.$conversation->id
            && $event->broadcastAs() === 'reception'
            && $keys === ['conversation_id', 'event', 'occurred_at']
            && $payload['event'] === 'visitor_message_created'
            && $payload['conversation_id'] === (string) $conversation->id;
    });
});

test('完整会话缺少收件箱线程时记录警告并停止广播', function () {
    Event::fake([InstanceReceptionUpdated::class, VisitorConversationUpdated::class]);

    $contact = Contact::factory()->create();
    $channel = Channel::factory()->create();
    $conversation = Conversation::withoutEvents(fn (): Conversation => Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->create());
    $log = Log::spy();

    expect(fn () => app(ReceptionRealtimeNotifier::class)
        ->conversationChanged($conversation, 'visitor_message_created'))
        ->toThrow(LogicException::class);

    Event::assertNotDispatched(InstanceReceptionUpdated::class);
    Event::assertNotDispatched(VisitorConversationUpdated::class);
    $log->shouldHaveReceived('warning')
        ->once()
        ->withArgs(
            fn (string $_message, array $context): bool => $context['event'] === 'visitor_message_created'
                && $context['conversation_id'] === (string) $conversation->id
                && $context['contact_id'] === (string) $contact->id
                && $context['channel_id'] === (string) $channel->id,
        );
});

test('它只向会话公开主题推送客服页面活动租约', function () {
    Event::fake([ReceptionAgentActivityUpdated::class, VisitorConversationUpdated::class, InstanceReceptionUpdated::class]);

    $conversation = createRealtimeThreadedConversation();

    app(ReceptionRealtimeNotifier::class)->teammateActivity($conversation, 'user-1:page-1', 3, true);

    Event::assertDispatched(ReceptionAgentActivityUpdated::class, function (ReceptionAgentActivityUpdated $event) use ($conversation): bool {
        return $event->conversationId === (string) $conversation->id
            && $event->broadcastOn()->name === 'urn:helmdesk:reception:conversation:'.$conversation->id
            && $event->broadcastAs() === 'agent_activity'
            && $event->broadcastWith()['conversation_id'] === (string) $conversation->id
            && $event->broadcastWith()['active'] === true
            && $event->broadcastWith()['hold_ms'] > 0
            && $event->broadcastWith()['hold_ms'] <= 8000
            && $event->revision > 0
            && $event->broadcastWith()['revision'] === $event->revision;
    });
    Event::assertNotDispatched(VisitorConversationUpdated::class);
    Event::assertNotDispatched(InstanceReceptionUpdated::class);
});

test('它推送 AI turn 排队执行与停止后的聚合状态', function () {
    Event::fake([ReceptionAgentActivityUpdated::class]);

    $notifier = app(ReceptionRealtimeNotifier::class);

    $notifier->aiTurnQueued('conv-1', 'activity-1');
    $notifier->aiTurnStarted('conv-1', 'activity-1');
    $notifier->aiTurnStopped('conv-1', 'activity-1');

    Event::assertDispatchedTimes(ReceptionAgentActivityUpdated::class, 3);
    $activityEvents = Event::dispatched(ReceptionAgentActivityUpdated::class)
        ->map(static fn (array $arguments): ReceptionAgentActivityUpdated => $arguments[0])
        ->values();
    expect($activityEvents[1]->revision)->toBeGreaterThan($activityEvents[0]->revision)
        ->and($activityEvents[2]->revision)->toBeGreaterThan($activityEvents[1]->revision);

    Event::assertDispatched(
        ReceptionAgentActivityUpdated::class,
        fn (ReceptionAgentActivityUpdated $event): bool => $event->active
            && $event->holdMilliseconds > 210000
            && $event->holdMilliseconds <= 330000,
    );
    Event::assertDispatched(
        ReceptionAgentActivityUpdated::class,
        fn (ReceptionAgentActivityUpdated $event): bool => $event->active
            && $event->holdMilliseconds > 0
            && $event->holdMilliseconds <= 210000,
    );
    Event::assertDispatched(
        ReceptionAgentActivityUpdated::class,
        fn (ReceptionAgentActivityUpdated $event): bool => ! $event->active
            && $event->holdMilliseconds === 0,
    );
});

test('它在单侧推送失败时记录日志且不阻塞另一侧推送', function () {
    Event::fake([VisitorConversationUpdated::class]);
    Event::listen(InstanceReceptionUpdated::class, function (): void {
        throw new RuntimeException('mercure unavailable');
    });
    $log = Log::spy();

    $conversation = createRealtimeThreadedConversation();

    // 坐席侧推送先派发且失败，不应抛出，也不应影响其后的访客侧推送。
    app(ReceptionRealtimeNotifier::class)->conversationChanged($conversation, 'visitor_message_created');

    Event::assertDispatched(VisitorConversationUpdated::class, function (VisitorConversationUpdated $event) use ($conversation): bool {
        return $event->conversationId === (string) $conversation->id;
    });
    $log->shouldHaveReceived('warning')->once();
});
