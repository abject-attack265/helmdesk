<?php

use App\Enums\ConversationInboxStatus;
use App\Events\Reception\InstanceReceptionUpdated;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationThread;
use App\Models\User;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

test('坐席端通知 payload 始终携带前端弹窗所需的会话字段', function () {
    Event::fake([InstanceReceptionUpdated::class]);

    $agent = User::factory()->create();
    $contact = Contact::factory()->visitor()->create([
        'name' => '访客小王',
    ]);
    $channel = Channel::factory()->create();
    $conversation = Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->create([
            'assigned_user_id' => $agent->id,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
            'last_message_preview' => '请问怎么退款',
        ])
        ->refresh();

    app(ReceptionRealtimeNotifier::class)->conversationChanged($conversation, 'visitor_message_created');

    Event::assertDispatched(InstanceReceptionUpdated::class, function (InstanceReceptionUpdated $event) use ($agent, $conversation): bool {
        $payload = $event->payload;

        $requiredKeys = [
            'event',
            'thread_id',
            'conversation_id',
            'assigned_user_id',
            'inbox_status',
            'last_message_preview',
            'contact_name',
        ];
        foreach ($requiredKeys as $key) {
            if (! array_key_exists($key, $payload)) {
                return false;
            }
        }

        return $payload['thread_id'] === (string) ConversationThread::requireForConversation($conversation)->id
            && $payload['conversation_id'] === (string) $conversation->id
            && $payload['assigned_user_id'] === (string) $agent->id
            && $payload['inbox_status'] === ConversationInboxStatus::TeammatePending->value
            && $payload['last_message_preview'] === '请问怎么退款'
            && $payload['contact_name'] === '访客小王';
    });
});

test('坐席端通知 payload 透传业务 meta', function () {
    Event::fake([InstanceReceptionUpdated::class]);

    $contact = Contact::factory()->create();
    $channel = Channel::factory()->create();
    $conversation = Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->create()
        ->refresh();

    app(ReceptionRealtimeNotifier::class)->conversationChanged($conversation, 'conversation_transferred', [
        'previous_assigned_user_id' => '01J0PREVIOUSAGENTULID000000',
        'message_id' => '01J0TRIGGERMESSAGEULID00000',
    ]);

    Event::assertDispatched(InstanceReceptionUpdated::class, function (InstanceReceptionUpdated $event): bool {
        $payload = $event->payload;

        return ($payload['previous_assigned_user_id'] ?? null) === '01J0PREVIOUSAGENTULID000000'
            && ($payload['message_id'] ?? null) === '01J0TRIGGERMESSAGEULID00000';
    });
});
