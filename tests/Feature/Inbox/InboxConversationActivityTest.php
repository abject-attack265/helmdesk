<?php

use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Events\Reception\ReceptionAgentActivityUpdated;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = $this->createUserWithInstance();
});

test('可回复会话的同事续期选中状态时向会话公开主题发布活动租约', function () {
    Event::fake([ReceptionAgentActivityUpdated::class]);

    $contact = Contact::factory()->create([]);
    $conversation = Conversation::factory()
        ->forContact($contact)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    $this->actingAs($this->user)
        ->postJson('/app/inbox/'.$conversation->id.'/activity', [
            'activity_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'sequence' => 1,
            'active' => true,
        ])
        ->assertNoContent();

    Event::assertDispatched(ReceptionAgentActivityUpdated::class, function (ReceptionAgentActivityUpdated $event) use ($conversation): bool {
        return $event->conversationId === (string) $conversation->id
            && $event->broadcastOn()->name === 'urn:helmdesk:reception:conversation:'.$conversation->id
            && $event->broadcastAs() === 'agent_activity'
            && $event->active
            && $event->holdMilliseconds > 0
            && $event->holdMilliseconds <= 8000
            && $event->revision > 0;
    });
});

test('无回复权限的同事不能建立会话活动租约', function () {
    Event::fake([ReceptionAgentActivityUpdated::class]);

    $otherAssignee = User::factory()->create();
    $contact = Contact::factory()->create([]);
    $conversation = Conversation::factory()
        ->forContact($contact)
        ->assignedTo($otherAssignee)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    $this->actingAs($this->user)
        ->postJson('/app/inbox/'.$conversation->id.'/activity', [
            'activity_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'sequence' => 1,
            'active' => true,
        ])
        ->assertNoContent();

    Event::assertNotDispatched(ReceptionAgentActivityUpdated::class);
});

test('客服失去回复权限后仍可释放自己的页面活动租约', function () {
    Event::fake([ReceptionAgentActivityUpdated::class]);

    $otherAssignee = User::factory()->create();
    $contact = Contact::factory()->create([]);
    $conversation = Conversation::factory()
        ->forContact($contact)
        ->assignedTo($otherAssignee)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    $this->actingAs($this->user)
        ->postJson('/app/inbox/'.$conversation->id.'/activity', [
            'activity_id' => 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
            'sequence' => 2,
            'active' => false,
        ])
        ->assertNoContent();

    Event::assertDispatched(
        ReceptionAgentActivityUpdated::class,
        fn (ReceptionAgentActivityUpdated $event): bool => ! $event->active
            && $event->holdMilliseconds === 0,
    );
});
