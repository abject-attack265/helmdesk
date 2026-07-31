<?php

use App\Actions\Reception\AppendTeammateMessageAction;
use App\Actions\Reception\CloseConversationAction;
use App\Actions\Reception\ReleaseConversationToAiAction;
use App\Actions\Reception\ReopenConversationAction;
use App\Data\Conversation\ConversationSummaryData;
use App\Enums\ChannelType;
use App\Enums\ConversationEventType;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Exceptions\BusinessException;
use App\Http\Middleware\HandleInertiaRequests;
use App\Jobs\Reception\RunReceptionTurnJob;
use App\Models\AiModel;
use App\Models\Attachment;
use App\Models\AttributeDefinition;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ContactAttributeValue;
use App\Models\ContactIdentity;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\ConversationMessage;
use App\Models\ConversationThread;
use App\Models\ReceptionPlan;
use App\Models\ReceptionPlanVersion;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use App\Services\Reception\ReceptionActivityRegistry;
use App\Settings\GeneralSettings;
use Database\Factories\ConversationFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = $this->createUserWithInstance();
});

/**
 * 创建系统接待对话模型。
 *
 * 传入 isActive=false 可造一个停用模型，使全局接待对话池为空，模拟「模型不可用」。
 */
function createInboxLlmModel(GeneralSettings $app, bool $isActive = true): AiModel
{
    return makeAiModel(isActive: $isActive);
}

/**
 * 构造一个接待方案版本并保证全局存在可用接待对话模型，供需要 AI 可用性的会话用例使用。
 */
function createInboxReceptionPlanVersion(GeneralSettings $app, ?AiModel $model = null): ReceptionPlanVersion
{
    $model ??= createInboxLlmModel($app);
    $plan = ReceptionPlan::factory()->create([
        'name' => '收件箱测试方案-'.Str::lower(Str::random(6)),
    ]);

    return ReceptionPlanVersion::factory()
        ->for($plan, 'plan')
        ->create();
}

/**
 * 构造联系人和渠道身份完整的会话工厂。
 */
function inboxThreadConversationFactory(Contact $contact, ?Channel $channel = null): ConversationFactory
{
    $channel ??= Channel::factory()->create();

    return Conversation::factory()->forContactChannel($contact, $channel);
}

/**
 * 返回会话所属的收件箱线程 ID。
 */
function inboxThreadId(Conversation $conversation): string
{
    return (string) ConversationThread::requireForConversation($conversation)->id;
}

test('收件箱默认进入待处理视图，让同事进入需要处理的队列', function () {
    $contact = Contact::factory()->create([
        'name' => 'Mia',
    ]);

    inboxThreadConversationFactory($contact)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
            'unread_visitor_message_count' => 2,
        ]);

    $needsHuman = inboxThreadConversationFactory($contact)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
            'assigned_user_id' => null,
        ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inbox')
            ->where('current_view', 'pending')
            ->where('current_channel_id', null)
            ->where('current_assignee', null)
            ->has('conversation_list', 1)
            ->where('conversation_list.0.id', $needsHuman->id)
            ->where('conversation_list.0.thread_id', inboxThreadId($needsHuman))
            ->where('current_pane', 'thread')
            ->where('current_thread_id', inboxThreadId($needsHuman))
            ->where('selection.conversation.id', $needsHuman->id)
            ->has('enabled_web_channels')
            ->has('teammates')
        );
});

test('会话列表下发联系人真实头像，占位头像下发 null 以回退昵称首字母', function () {
    $syncedContact = Contact::factory()->create([
        'name' => 'Telegram Tina',
        'avatar_url' => 'https://cdn.example.com/telegram-avatar.jpg',
        'avatar_synced_at' => now(),
    ]);
    $placeholderContact = Contact::factory()->create([
        'name' => 'Placeholder Pam',
    ]);

    $syncedConversation = inboxThreadConversationFactory($syncedContact)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
            'last_message_at' => now(),
        ]);
    $placeholderConversation = inboxThreadConversationFactory($placeholderContact)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
            'last_message_at' => now()->subDay(),
        ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=pending')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversation_list.0.id', $syncedConversation->id)
            ->where('conversation_list.0.contact_avatar_url', 'https://cdn.example.com/telegram-avatar.jpg')
            ->where('conversation_list.1.id', $placeholderConversation->id)
            ->where('conversation_list.1.contact_avatar_url', null)
        );
});

test('收件箱支持重点客户筛选并在开放视图优先显示重点客户', function () {
    $importantContact = Contact::factory()->create([
        'name' => 'Important Mia',
        'is_important' => true,
        'important_at' => now()->subDay(),
        'important_source' => 'manual',
    ]);
    $normalContact = Contact::factory()->create([
        'name' => 'Normal Nora',
    ]);

    $importantConversation = inboxThreadConversationFactory($importantContact)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
            'last_message_at' => now()->subDays(2),
        ]);
    $normalConversation = inboxThreadConversationFactory($normalContact)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
            'last_message_at' => now(),
        ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=pending')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_important_only', false)
            ->where('conversation_list.0.id', $importantConversation->id)
            ->where('conversation_list.0.contact_is_important', true)
            ->where('conversation_list.1.id', $normalConversation->id)
        );

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=pending&important=1')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_important_only', true)
            ->has('conversation_list', 1)
            ->where('conversation_list.0.id', $importantConversation->id)
            ->where('conversation_list.0.contact_is_important', true)
        );
});

test('收件箱拒绝无效视图查询', function () {
    $this->actingAs($this->user)
        ->from('/app'.'/inbox')
        ->get('/app'.'/inbox?view=unknown')
        ->assertRedirect('/app'.'/inbox')
        ->assertSessionHasErrors('view');
});

test('收件箱选中项会把同一联系人的所有会话合并为单一时间线', function () {
    $this->user->forceFill([
        'avatar' => 'https://example.com/operator.png',
    ])->save();

    $contact = Contact::factory()->create([
        'name' => 'Nova',
    ]);
    $channel = Channel::factory()->create();

    $oldClosed = inboxThreadConversationFactory($contact, $channel)
        ->assignedTo($this->user)
        ->closed()
        ->create([]);

    ConversationMessage::query()->create([
        'conversation_id' => $oldClosed->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'Hello from old conversation',
    ]);

    $openNow = inboxThreadConversationFactory($contact, $channel)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $openNow->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'Hi again, new question',
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $openNow->id,
        'sender_user_id' => $this->user->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => 'I can help from Nova Support',
    ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=mine&thread_id='.inboxThreadId($openNow))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($contact, $openNow) {
            $entries = collect($page->toArray()['props']['selection']['stitched_timeline']['entries']);
            $teammateEntry = $entries->firstWhere('content', 'I can help from Nova Support');

            expect($teammateEntry)->not->toBeNull()
                ->and($teammateEntry['sender_name'])->toBe($this->user->name)
                ->and($teammateEntry['sender_avatar_url'])->toBe('https://example.com/operator.png');

            $page
                ->where('current_pane', 'thread')
                ->where('current_thread_id', inboxThreadId($openNow))
                ->where('selection.conversation.id', $openNow->id)
                ->where('selection.conversation.assigned_user_id', $this->user->id)
                ->where('selection.conversation.assigned_user_name', $this->user->name)
                ->where('selection.contact.id', $contact->id)
                ->has('selection.stitched_timeline.conversations', 2)
                ->has('selection.stitched_timeline.entries')
                ->where('selection.can_reply', true);
        });
});

test('会话摘要数据仅在加载渠道关系时暴露渠道身份', function () {
    $contact = Contact::factory()->create([
        'name' => 'Mia',
    ]);

    $channel = Channel::factory()->telegram()->create([
        'name' => 'Nova Support Bot',
    ]);

    $conversation = inboxThreadConversationFactory($contact, $channel)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    // 未加载渠道关系时不暴露渠道身份。
    $withoutChannel = ConversationSummaryData::fromModel($conversation);
    expect($withoutChannel->channel_type)->toBeNull()
        ->and($withoutChannel->channel_type_label)->toBeNull()
        ->and($withoutChannel->channel_name)->toBeNull();

    // 加载渠道关系后带出渠道身份用于多渠道上下文头。
    $conversation->load('channel');
    $withChannel = ConversationSummaryData::fromModel($conversation);
    expect($withChannel->channel_type)->toBe(ChannelType::Telegram)
        ->and($withChannel->channel_type_label)->toBe('Telegram')
        ->and($withChannel->channel_name)->toBe('Nova Support Bot');
});

test('收件箱选择暴露联系人标签和自定义属性用于配置档面板', function () {
    $contact = Contact::factory()->create([
        'name' => 'Profile Contact',
        'note' => 'VIP customer',
        'is_important' => true,
        'important_at' => now()->subDay(),
        'important_source' => 'manual',
    ]);
    $conversation = inboxThreadConversationFactory($contact)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    $contactGroup = TagGroup::factory()->contact()->create([]);
    $conversationGroup = TagGroup::factory()->conversation()->create([]);
    $contactTag = Tag::factory()->forGroup($contactGroup)->create(['name' => 'Important']);
    $conversationTag = Tag::factory()->forGroup($conversationGroup)->create(['name' => 'Conversation Only']);
    $emailIdentity = ContactIdentity::factory()->email('profile@example.com')->create([
        'contact_id' => $contact->id,
    ]);
    $phoneIdentity = ContactIdentity::factory()->phone('+8613800000000')->create([
        'contact_id' => $contact->id,
    ]);
    $contact->syncPrimaryFields();

    DB::table('contact_tag_assignments')->insert([
        'tag_id' => $contactTag->id,
        'contact_id' => $contact->id,
        'assigned_by_user_id' => $this->user->id,
        'source' => 'manual',
        'created_at' => now(),
    ]);

    $definition = AttributeDefinition::factory()->text()->create([
        'key' => 'plan',
        'name' => 'Plan',
    ]);
    ContactAttributeValue::factory()->forText('Enterprise')->create([
        'contact_id' => $contact->id,
        'definition_id' => $definition->id,
    ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=mine&thread_id='.inboxThreadId($conversation))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($conversation, $contactTag, $conversationTag, $emailIdentity, $phoneIdentity) {
            $props = $page->toArray()['props'];
            $availableContactTagIds = collect($props['available_contact_tags'])->pluck('id')->all();
            $availableConversationTagIds = collect($props['available_conversation_tags'])->pluck('id')->all();

            // 标签选项按维度分流：联系人选择器只出联系人标签，会话选择器只出会话标签。
            expect($availableContactTagIds)
                ->toContain($contactTag->id)
                ->not->toContain($conversationTag->id);
            expect($availableConversationTagIds)
                ->toContain($conversationTag->id)
                ->not->toContain($contactTag->id);

            $page
                ->where('current_pane', 'thread')
                ->where('current_thread_id', inboxThreadId($conversation))
                ->where('selection.conversation.id', $conversation->id)
                ->where('selection.contact_profile.note', 'VIP customer')
                ->where('selection.contact.is_important', true)
                ->where('selection.contact_profile.is_important', true)
                ->where('selection.contact_profile.primary_email', 'profile@example.com')
                ->where('selection.contact_profile.primary_email_identity_id', $emailIdentity->id)
                ->where('selection.contact_profile.primary_phone', '+8613800000000')
                ->where('selection.contact_profile.primary_phone_identity_id', $phoneIdentity->id)
                ->where('selection.contact_profile.tags.0.id', $contactTag->id)
                ->where('selection.contact_profile.custom_attributes.0.key', 'plan')
                ->where('selection.contact_profile.custom_attributes.0.value', 'Enterprise');
        });
});

test('收件箱选中会话下发会话标签，且联系人资料带咨询概况聚合', function () {
    $contact = Contact::factory()->create([]);
    $conversation = inboxThreadConversationFactory($contact)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    $group = TagGroup::factory()->conversation()->create([]);
    $refund = Tag::factory()->forGroup($group)->create(['name' => '退款']);

    DB::table('conversation_tag_assignments')->insert([
        'conversation_id' => $conversation->id,
        'tag_id' => $refund->id,
        'source' => 'ai',
        'confidence' => 0.91,
        'reason' => '客户要求退款',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    ConversationMessage::factory()
        ->forConversation($conversation)
        ->create([
            'role' => MessageRole::Visitor,
            'kind' => MessageKind::Text,
            'content' => '我想申请退款',
        ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=mine&thread_id='.inboxThreadId($conversation))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($conversation, $refund) {
            $currentTags = collect($page->toArray()['props']['selection']['conversation']['tags']);
            expect($currentTags->pluck('id')->all())->toContain($refund->id);
            expect($currentTags->firstWhere('id', $refund->id)['source'])->toBe('ai');

            $conversations = collect(
                $page->toArray()['props']['selection']['stitched_timeline']['conversations'],
            );
            $current = $conversations->firstWhere('id', $conversation->id);
            expect($current)->not->toBeNull();

            expect(collect($current['tags'])->pluck('id')->all())->toContain($refund->id);
            expect(collect($current['tags'])->firstWhere('id', $refund->id)['source'])->toBe('ai');

            $aggregates = collect(
                $page->toArray()['props']['selection']['contact_profile']['conversation_tag_aggregates'],
            );
            expect($aggregates->firstWhere('tag_id', $refund->id)['count'])->toBe(1);
        });
});

test('同事可以回复收件箱会话并连接到AppendTeammateMessageAction', function () {
    $contact = Contact::factory()->create([]);
    $conversation = inboxThreadConversationFactory($contact)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$conversation->id.'/reply', [
            'content' => 'Got it, looking into this now.',
            'kind' => 'text',
        ])
        ->assertRedirect();

    $message = ConversationMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('role', MessageRole::Teammate)
        ->first();

    expect($message)->not->toBeNull()
        ->and($message->content)->toBe('Got it, looking into this now.');

    $conversation->refresh();
    expect($conversation->inbox_status)->toBe(ConversationInboxStatus::TeammateHandling)
        ->and($conversation->waiting_for_visitor_reply)->toBeTrue()
        ->and($conversation->unread_visitor_message_count)->toBe(0);
});

test('同事可以回复并发送仅附件文件消息', function () {
    $contact = Contact::factory()->create([]);
    $conversation = inboxThreadConversationFactory($contact)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);
    $attachment = Attachment::factory()->create([
        'uploaded_by_user_id' => $this->user->id,
        'purpose' => 'conversation_file',
        'original_name' => 'manual.pdf',
        'mime_type' => 'application/pdf',
        'extension' => 'pdf',
        'byte_size' => 2048,
        'status' => 'uploaded',
    ]);

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$conversation->id.'/reply', [
            'content' => '',
            'attachment_ids' => [$attachment->id],
        ])
        ->assertRedirect();

    $message = ConversationMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('role', MessageRole::Teammate)
        ->firstOrFail();

    expect($message->kind)->toBe(MessageKind::File)
        ->and($message->content)->toBeNull()
        ->and($message->payload['attachments'][0]['id'])->toBe((string) $attachment->id)
        ->and($attachment->fresh()->attachable_id)->toBe($message->id);
});

test('回复需要人工处理的会话会将操作员移到我的视图并选中该会话', function () {
    $contact = Contact::factory()->create([]);
    $conversation = Conversation::factory()
        ->withReceptionPlanVersion()
        ->forContact($contact)
        ->create([
            'assigned_user_id' => null,
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
        ]);

    $redirectTo = route('app.inbox.show', [
        'view' => 'mine',
        'thread_id' => inboxThreadId($conversation),
    ], false);

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$conversation->id.'/reply', [
            'content' => '我来处理，稍等一下。',
        ])
        ->assertRedirect($redirectTo);

    $conversation->refresh();
    expect((string) $conversation->assigned_user_id)->toBe((string) $this->user->id)
        ->and($conversation->inbox_status)->toBe(ConversationInboxStatus::TeammateHandling)
        ->and($conversation->waiting_for_visitor_reply)->toBeTrue();

    $this->actingAs($this->user)
        ->get($redirectTo)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_view', 'mine')
            ->where('current_thread_id', inboxThreadId($conversation))
            ->where('conversation_list.0.id', $conversation->id)
            ->where('conversation_list.0.thread_id', inboxThreadId($conversation))
            ->where('selection.conversation.id', $conversation->id)
            ->where('selection.conversation.assigned_user_id', $this->user->id)
            ->has('selection.stitched_timeline.entries')
        );

    // 隐式认领后会话离开排队中视图。
    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=pending')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('conversation_list', 0));
});

test('回复来自收件箱刷新同事最后活跃时间戳', function () {
    $contact = Contact::factory()->create([]);
    $conversation = Conversation::factory()
        ->withReceptionPlanVersion()
        ->forContact($contact)
        ->create([
            'assigned_user_id' => null,
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
        ]);

    $previousLastActiveAt = now()->subDay();
    $this->user->membership()->update([
        'last_active_at' => $previousLastActiveAt,
    ]);

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$conversation->id.'/reply', [
            'content' => '马上帮你处理。',
        ])
        ->assertRedirect();

    $updatedLastActiveAt = DB::table('memberships')

        ->where('user_id', $this->user->id)
        ->value('last_active_at');

    expect($updatedLastActiveAt)->not->toBeNull()
        ->and(Carbon::parse((string) $updatedLastActiveAt)->isAfter($previousLastActiveAt))->toBeTrue();
});

test('同事可以认领teammate_pending会话来自收件箱', function () {
    $contact = Contact::factory()->create([]);
    $conversation = Conversation::factory()
        ->withReceptionPlanVersion()
        ->forContact($contact)
        ->create([
            'assigned_user_id' => null,
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
        ]);

    $redirectTo = route('app.inbox.show', [
        'view' => 'mine',
        'thread_id' => inboxThreadId($conversation),
    ], false);

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$conversation->id.'/claim')
        ->assertRedirect($redirectTo);

    $conversation->refresh();
    expect($conversation->inbox_status)->toBe(ConversationInboxStatus::TeammateHandling)
        ->and((string) $conversation->assigned_user_id)->toBe((string) $this->user->id);
});

test('同事可以转移AI会话到人工来自收件箱', function () {
    $contact = Contact::factory()->create([]);
    $conversation = Conversation::factory()
        ->withReceptionPlanVersion()
        ->forContact($contact)
        ->create([
            'assigned_user_id' => null,
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::AiHandling,
        ]);

    $redirectTo = route('app.inbox.show', [
        'view' => 'mine',
        'thread_id' => inboxThreadId($conversation),
    ], false);

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$conversation->id.'/claim')
        ->assertRedirect($redirectTo);

    $conversation->refresh();
    expect($conversation->inbox_status)->toBe(ConversationInboxStatus::TeammateHandling)
        ->and((string) $conversation->assigned_user_id)->toBe((string) $this->user->id);
});

test('AI会话需要先转人工后同事才能回复', function () {
    $contact = Contact::factory()->create([]);
    $conversation = Conversation::factory()
        ->withReceptionPlanVersion()
        ->forContact($contact)
        ->create([
            'assigned_user_id' => null,
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::AiHandling,
        ]);

    app(ReceptionActivityRegistry::class)->renew(
        (string) $conversation->id,
        'ai:turn:test',
        210000,
    );

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=ai&thread_id='.inboxThreadId($conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selection.conversation.id', $conversation->id)
            ->where('selection.agent_activity.active', true)
            ->where('selection.agent_activity.revision', fn (int $revision): bool => $revision > 0)
            ->where('selection.can_claim', true)
            ->where('selection.can_reply', false)
        );

    expect(fn () => app(AppendTeammateMessageAction::class)->handle(
        conversation: $conversation,
        actor: $this->user,
        content: 'I will take this one.',
    ))->toThrow(BusinessException::class);

    $conversation->refresh();
    expect($conversation->inbox_status)->toBe(ConversationInboxStatus::AiHandling)
        ->and($conversation->assigned_user_id)->toBeNull();
});

test('AI视图包含等待访客的未分配会话', function () {
    $contact = Contact::factory()->create([]);
    $conversation = Conversation::factory()
        ->withReceptionPlanVersion()
        ->forContact($contact)
        ->create([
            'assigned_user_id' => null,
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::AiHandling,
            'waiting_for_visitor_reply' => true,
        ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=ai&thread_id='.inboxThreadId($conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_view', 'ai')
            ->has('conversation_list', 1)
            ->where('conversation_list.0.id', $conversation->id)
            ->where('selection.conversation.id', $conversation->id)
            ->where('selection.can_claim', true)
            ->where('selection.can_reply', false)
        );

    expect(fn () => app(AppendTeammateMessageAction::class)->handle(
        conversation: $conversation,
        actor: $this->user,
        content: 'I will take this one.',
    ))->toThrow(BusinessException::class);

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$conversation->id.'/claim')
        ->assertRedirect(route('app.inbox.show', [
            'view' => 'mine',
            'thread_id' => inboxThreadId($conversation),
        ], false));

    $conversation->refresh();
    expect($conversation->inbox_status)->toBe(ConversationInboxStatus::TeammateHandling)
        ->and((string) $conversation->assigned_user_id)->toBe((string) $this->user->id)
        ->and($conversation->waiting_for_visitor_reply)->toBeTrue();
});

test('同事视图会将同事处理中的会话显示为可接管候选项', function () {
    $contact = Contact::factory()->create([]);
    $teammate = User::factory()->create();
    $this->attachInstance($teammate, $this->instance);

    $colleagueConversation = inboxThreadConversationFactory($contact)
        ->assignedTo($teammate)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    inboxThreadConversationFactory($contact)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    inboxThreadConversationFactory($contact)
        ->create([
            'assigned_user_id' => null,
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::AiHandling,
        ]);

    inboxThreadConversationFactory($contact)
        ->closed()
        ->create([]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=teammates&thread_id='.inboxThreadId($colleagueConversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_view', 'teammates')
            ->has('conversation_list', 1)
            ->where('conversation_list.0.id', $colleagueConversation->id)
            ->where('selection.conversation.id', $colleagueConversation->id)
            ->where('selection.can_claim', true)
            ->where('selection.can_reply', false)
            ->where('selection.can_release_to_ai', false)
            ->where('selection.can_close', false)
        );
});

test('同事可以接管同事处理中会话来自收件箱', function () {
    $contact = Contact::factory()->create([]);
    $teammate = User::factory()->create();
    $this->attachInstance($teammate, $this->instance);

    $conversation = Conversation::factory()
        ->withReceptionPlanVersion()
        ->forContact($contact)
        ->assignedTo($teammate)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    $redirectTo = route('app.inbox.show', [
        'view' => 'mine',
        'thread_id' => inboxThreadId($conversation),
    ], false);

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$conversation->id.'/claim')
        ->assertRedirect($redirectTo);

    $conversation->refresh();
    $event = ConversationEvent::query()
        ->where('conversation_id', $conversation->id)
        ->where('type', ConversationEventType::AssignmentChanged)
        ->latest('created_at')
        ->firstOrFail();

    expect($conversation->inbox_status)->toBe(ConversationInboxStatus::TeammateHandling)
        ->and((string) $conversation->assigned_user_id)->toBe((string) $this->user->id)
        ->and($event->payload['source'])->toBe('takeover')
        ->and($event->payload['previous_user_id'])->toBe((string) $teammate->id)
        ->and($event->payload['user_id'])->toBe((string) $this->user->id);
});

test('同事可以转移其已分配会话到已选择同事', function () {
    $contact = Contact::factory()->create([]);
    $targetTeammate = User::factory()->create();
    $this->attachInstance($targetTeammate, $this->instance);

    $conversation = Conversation::factory()
        ->withReceptionPlanVersion()
        ->forContact($contact)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=mine&thread_id='.inboxThreadId($conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selection.conversation.id', $conversation->id)
            ->where('selection.can_transfer_to_teammate', true)
        );

    $redirectTo = route('app.inbox.show', [
        'view' => 'teammates',
        'thread_id' => inboxThreadId($conversation),
    ], false);

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$conversation->id.'/transfer', [
            'target_user_id' => $targetTeammate->id,
        ])
        ->assertRedirect($redirectTo);

    $conversation->refresh();
    $event = ConversationEvent::query()
        ->where('conversation_id', $conversation->id)
        ->where('type', ConversationEventType::AssignmentChanged)
        ->latest('created_at')
        ->firstOrFail();

    expect($conversation->inbox_status)->toBe(ConversationInboxStatus::TeammateHandling)
        ->and((string) $conversation->assigned_user_id)->toBe((string) $targetTeammate->id)
        ->and($event->payload['source'])->toBe('transfer_to_teammate')
        ->and($event->payload['previous_user_id'])->toBe((string) $this->user->id)
        ->and($event->payload['user_id'])->toBe((string) $targetTeammate->id);

    $this->actingAs($this->user)
        ->get($redirectTo)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_view', 'teammates')
            ->where('current_assignee', null)
            ->where('selection.conversation.assigned_user_id', $targetTeammate->id)
            ->where('selection.can_claim', true)
            ->where('selection.can_reply', false)
        );
});

test('同事不能转移会话已分配到同事', function () {
    $contact = Contact::factory()->create([]);
    $ownerTeammate = User::factory()->create();
    $targetTeammate = User::factory()->create();
    $this->attachInstance($ownerTeammate, $this->instance);
    $this->attachInstance($targetTeammate, $this->instance);

    $conversation = inboxThreadConversationFactory($contact)
        ->assignedTo($ownerTeammate)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$conversation->id.'/transfer', [
            'target_user_id' => $targetTeammate->id,
        ])
        ->assertStatus(422)
        ->assertJson([
            'message' => '只能转接自己正在接待的会话。',
        ]);

    $conversation->refresh();
    expect((string) $conversation->assigned_user_id)->toBe((string) $ownerTeammate->id);
});

test('同事视图可以被缩小到指定同事', function () {
    $contact = Contact::factory()->create([]);
    $targetTeammate = User::factory()->create();
    $otherTeammate = User::factory()->create();
    $this->attachInstance($targetTeammate, $this->instance);
    $this->attachInstance($otherTeammate, $this->instance);

    $matchingConversation = inboxThreadConversationFactory($contact)
        ->assignedTo($targetTeammate)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    inboxThreadConversationFactory($contact)
        ->assignedTo($otherTeammate)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=teammates&assignee='.$targetTeammate->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_view', 'teammates')
            ->where('current_assignee', $targetTeammate->id)
            ->has('conversation_list', 1)
            ->where('conversation_list.0.id', $matchingConversation->id)
        );
});

test('同事可以释放其已分配会话回到AI在回复后', function () {
    $version = createInboxReceptionPlanVersion($this->instance);
    $channel = Channel::factory()->create([
        'reception_plan_id' => $version->reception_plan_id,
    ]);
    $contact = Contact::factory()->create([]);
    $conversation = inboxThreadConversationFactory($contact, $channel)
        ->assignedTo($this->user)
        ->create([
            'reception_plan_version_id' => $version->id,
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
            'waiting_for_visitor_reply' => true,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'sender_user_id' => $this->user->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => 'Please check and reply later.',
    ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=mine&thread_id='.inboxThreadId($conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selection.conversation.id', $conversation->id)
            ->where('selection.can_release_to_ai', true)
            ->where('selection.release_to_ai_will_use_ai', true)
        );

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$conversation->id.'/release-to-ai')
        ->assertRedirect(route('app.inbox.show', [
            'view' => 'ai',
            'thread_id' => inboxThreadId($conversation),
        ], false));

    $conversation->refresh();
    expect($conversation->assigned_user_id)->toBeNull()
        ->and($conversation->inbox_status)->toBe(ConversationInboxStatus::AiHandling)
        ->and($conversation->waiting_for_visitor_reply)->toBeTrue();
});

test('访客消息后释放给AI会让AI准备回答', function () {
    Queue::fake();
    $version = createInboxReceptionPlanVersion($this->instance);
    $channel = Channel::factory()->create([
        'reception_plan_id' => $version->reception_plan_id,
    ]);
    $contact = Contact::factory()->create([]);
    $conversation = inboxThreadConversationFactory($contact, $channel)
        ->assignedTo($this->user)
        ->create([
            'reception_plan_version_id' => $version->id,
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'Can AI continue from here?',
    ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=mine&thread_id='.inboxThreadId($conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selection.conversation.id', $conversation->id)
            ->where('selection.can_release_to_ai', true)
            ->where('selection.release_to_ai_will_use_ai', true)
        );

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$conversation->id.'/release-to-ai')
        ->assertRedirect(route('app.inbox.show', [
            'view' => 'ai',
            'thread_id' => inboxThreadId($conversation),
        ], false));

    $conversation->refresh();
    expect($conversation->assigned_user_id)->toBeNull()
        ->and($conversation->inbox_status)->toBe(ConversationInboxStatus::AiHandling)
        ->and($conversation->waiting_for_visitor_reply)->toBeFalse();

    // 「准备回答」= 释放后立即派发补答 turn，AI 直接回应积压的访客消息。
    Queue::assertPushed(
        RunReceptionTurnJob::class,
        fn (RunReceptionTurnJob $job): bool => $job->conversationId === (string) $conversation->id,
    );
});

test('排队中的会话可从收件箱直接交给 AI', function () {
    Queue::fake();
    $version = createInboxReceptionPlanVersion($this->instance);
    $channel = Channel::factory()->create([
        'reception_plan_id' => $version->reception_plan_id,
    ]);
    $contact = Contact::factory()->create([]);
    $conversation = inboxThreadConversationFactory($contact, $channel)
        ->create([
            'reception_plan_version_id' => $version->id,
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
            'assigned_user_id' => null,
        ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=pending&thread_id='.inboxThreadId($conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selection.conversation.id', $conversation->id)
            ->where('selection.can_release_to_ai', true)
            ->where('selection.release_to_ai_will_use_ai', true)
        );

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$conversation->id.'/release-to-ai')
        ->assertRedirect(route('app.inbox.show', [
            'view' => 'ai',
            'thread_id' => inboxThreadId($conversation),
        ], false));

    $conversation->refresh();
    expect($conversation->inbox_status)->toBe(ConversationInboxStatus::AiHandling)
        ->and($conversation->assigned_user_id)->toBeNull();
});

test('频道模型不可用时释放给AI会回退到待处理队列', function () {
    $model = createInboxLlmModel($this->instance, isActive: false);
    $version = createInboxReceptionPlanVersion($this->instance, $model);
    $channel = Channel::factory()->create([
        'reception_plan_id' => $version->reception_plan_id,
    ]);
    $contact = Contact::factory()->create([]);
    $conversation = inboxThreadConversationFactory($contact, $channel)
        ->assignedTo($this->user)
        ->create([
            'reception_plan_version_id' => $version->id,
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'Can AI continue from here?',
    ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=mine&thread_id='.inboxThreadId($conversation))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('selection.conversation.id', $conversation->id)
            ->where('selection.can_release_to_ai', true)
            ->where('selection.release_to_ai_will_use_ai', false)
        );

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$conversation->id.'/release-to-ai')
        ->assertRedirect(route('app.inbox.show', [
            'view' => 'pending',
            'thread_id' => inboxThreadId($conversation),
        ], false));

    $conversation->refresh();
    expect($conversation->assigned_user_id)->toBeNull()
        ->and($conversation->inbox_status)->toBe(ConversationInboxStatus::TeammatePending)
        ->and($conversation->waiting_for_visitor_reply)->toBeFalse();
});

test('释放会话按事务内最新负责人校验权限', function () {
    $conversation = Conversation::factory()
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);
    $staleConversation = $conversation->fresh();
    $newOwner = User::factory()->create();

    $conversation->update(['assigned_user_id' => $newOwner->id]);

    expect(fn () => app(ReleaseConversationToAiAction::class)->handle($staleConversation, $this->user))
        ->toThrow(BusinessException::class, __('conversation.errors.release_to_ai_not_allowed'));

    expect($conversation->fresh()->assigned_user_id)->toBe($newOwner->id)
        ->and($conversation->fresh()->inbox_status)->toBe(ConversationInboxStatus::TeammateHandling);
});

test('同事可以直接关闭会话', function () {
    $contact = Contact::factory()->create([]);
    $conversation = inboxThreadConversationFactory($contact)
        ->create([
            'assigned_user_id' => null,
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
        ]);

    $redirectTo = route('app.inbox.show', [
        'view' => 'closed',
        'thread_id' => inboxThreadId($conversation),
    ], false);

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$conversation->id.'/close')
        ->assertRedirect($redirectTo);

    $conversation->refresh();
    expect($conversation->status)->toBe(ConversationStatus::Closed)
        ->and($conversation->closed_at)->not->toBeNull();

    $this->actingAs($this->user)
        ->get($redirectTo)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_view', 'closed')
            ->where('current_pane', 'thread')
            ->where('current_thread_id', inboxThreadId($conversation))
            ->where('conversation_list.0.id', $conversation->id)
            ->where('conversation_list.0.thread_id', inboxThreadId($conversation))
            ->where('selection.conversation.id', $conversation->id)
            ->where('selection.can_reply', false)
            ->where('selection.can_reopen', true)
        );
});

test('关闭会话按事务内最新负责人校验权限', function () {
    $conversation = Conversation::factory()
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);
    $staleConversation = $conversation->fresh();
    $newOwner = User::factory()->create();

    $conversation->update(['assigned_user_id' => $newOwner->id]);

    expect(fn () => app(CloseConversationAction::class)->handle($staleConversation, $this->user))
        ->toThrow(BusinessException::class, __('conversation.errors.close_not_allowed_for_assignee'));

    expect($conversation->fresh()->status)->toBe(ConversationStatus::Open);
});

test('同事可以重新打开已关闭会话来自收件箱', function () {
    $contact = Contact::factory()->create([]);
    $channel = Channel::factory()->create([]);
    $conversation = inboxThreadConversationFactory($contact, $channel)
        ->assignedTo($this->user)
        ->closed()
        ->create();

    $redirectTo = route('app.inbox.show', [
        'view' => 'mine',
        'thread_id' => inboxThreadId($conversation),
    ], false);

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$conversation->id.'/reopen')
        ->assertRedirect($redirectTo);

    $conversation->refresh();
    expect($conversation->status)->toBe(ConversationStatus::Open)
        ->and($conversation->inbox_status)->toBe(ConversationInboxStatus::TeammateHandling)
        ->and($conversation->assigned_user_id)->toBe($this->user->id)
        ->and($conversation->closed_at)->toBeNull()
        ->and($conversation->reopened_at)->not->toBeNull();
});

test('重新打开会话按事务内最新状态拒绝重复操作', function () {
    $conversation = Conversation::factory()->closed()->create();
    $staleConversation = $conversation->fresh();

    $conversation->update([
        'status' => ConversationStatus::Open,
        'closed_at' => null,
    ]);
    $eventCount = ConversationEvent::query()
        ->where('conversation_id', $conversation->id)
        ->count();

    expect(fn () => app(ReopenConversationAction::class)->handle($staleConversation, $this->user))
        ->toThrow(BusinessException::class, __('conversation.errors.already_open'));

    expect(ConversationEvent::query()->where('conversation_id', $conversation->id)->count())->toBe($eventCount);
});

test('收件箱只允许重新打开线程当前代表会话', function () {
    $contact = Contact::factory()->create([]);
    $channel = Channel::factory()->create([]);
    $closedConversation = inboxThreadConversationFactory($contact, $channel)
        ->assignedTo($this->user)
        ->closed()
        ->create();

    inboxThreadConversationFactory($contact, $channel)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::AiHandling,
        ]);

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$closedConversation->id.'/reopen')
        ->assertNotFound();

    $closedConversation->refresh();
    expect($closedConversation->status)->toBe(ConversationStatus::Closed);
});

test('同一联系人渠道出现新的打开会话后线程选择解析到当前代表会话', function () {
    $contact = Contact::factory()->create([]);
    $channel = Channel::factory()->create([]);

    $closedConversation = inboxThreadConversationFactory($contact, $channel)
        ->assignedTo($this->user)
        ->closed()
        ->create();

    $openConversation = inboxThreadConversationFactory($contact, $channel)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::AiHandling,
        ]);
    $threadId = inboxThreadId($openConversation);

    $this->actingAs($this->user)
        ->get(route('app.inbox.show', [
            'view' => 'closed',
            'thread_id' => $threadId,
        ], false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_view', 'closed')
            ->where('current_pane', 'thread')
            ->has('conversation_list', 0)
            ->where('current_thread_id', $threadId)
            ->where('selection.conversation.id', $openConversation->id)
        );

    expect(inboxThreadId($closedConversation))->toBe($threadId);
});

test('已关闭收件箱仅保留同一联系人和频道的最新已关闭会话', function () {
    $contact = Contact::factory()->create([]);
    $channelA = Channel::factory()->create(['name' => 'Channel A']);
    $channelB = Channel::factory()->create(['name' => 'Channel B']);

    inboxThreadConversationFactory($contact, $channelA)
        ->assignedTo($this->user)
        ->closed()
        ->create([
            'closed_at' => now()->subDays(3),
            'last_message_at' => now()->subDays(3),
            'created_at' => now()->subDays(4),
        ]);

    inboxThreadConversationFactory($contact, $channelA)
        ->assignedTo($this->user)
        ->closed()
        ->create([
            'closed_at' => now()->subDays(2),
            'last_message_at' => now()->subDays(2),
            'created_at' => now()->subDays(3),
        ]);

    $latestOnChannelA = inboxThreadConversationFactory($contact, $channelA)
        ->assignedTo($this->user)
        ->closed()
        ->create([
            'closed_at' => now()->subDay(),
            'last_message_at' => now()->subDay(),
            'created_at' => now()->subDays(2),
        ]);

    $closedOnChannelB = inboxThreadConversationFactory($contact, $channelB)
        ->assignedTo($this->user)
        ->closed()
        ->create([
            'closed_at' => now()->subDays(4),
            'last_message_at' => now()->subDays(4),
            'created_at' => now()->subDays(5),
        ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=closed')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_view', 'closed')
            ->has('conversation_list', 2)
            ->where('conversation_list.0.id', $latestOnChannelA->id)
            ->where('conversation_list.1.id', $closedOnChannelB->id)
        );
});

test('已关闭视图默认展示应用内系统关闭的未分配会话', function () {
    $contactA = Contact::factory()->create([]);
    $contactB = Contact::factory()->create([]);
    $contactC = Contact::factory()->create([]);
    $teammate = User::factory()->create();
    $this->attachInstance($teammate, $this->instance);

    $systemClosedLatest = inboxThreadConversationFactory($contactA)
        ->closed()
        ->create([
            'assigned_user_id' => null,
            'closed_at' => now()->subMinute(),
            'last_message_at' => now()->subMinute(),
            'created_at' => now()->subHours(3),
        ]);

    $systemClosedOlder = inboxThreadConversationFactory($contactB)
        ->closed()
        ->create([
            'assigned_user_id' => null,
            'closed_at' => now()->subMinutes(2),
            'last_message_at' => now()->subMinutes(2),
            'created_at' => now()->subHours(4),
        ]);

    $teammateClosed = inboxThreadConversationFactory($contactC)
        ->assignedTo($teammate)
        ->closed()
        ->create([
            'closed_at' => now()->subMinutes(3),
            'last_message_at' => now()->subMinutes(3),
            'created_at' => now()->subHours(5),
        ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=closed')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_view', 'closed')
            ->has('conversation_list', 3)
            ->where('conversation_list.0.id', $systemClosedLatest->id)
            ->where('conversation_list.1.id', $systemClosedOlder->id)
            ->where('conversation_list.2.id', $teammateClosed->id)
        );
});

test('我的视图结合并带频道筛选缩小已分配列表到频道只', function () {
    $contact = Contact::factory()->create([]);
    $channelA = Channel::factory()->create(['name' => 'Channel A']);
    $channelB = Channel::factory()->create(['name' => 'Channel B']);

    $assignedOnA = inboxThreadConversationFactory($contact, $channelA)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    inboxThreadConversationFactory($contact, $channelB)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=mine&channel='.$channelA->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_view', 'mine')
            ->where('current_channel_id', $channelA->id)
            ->has('conversation_list', 1)
            ->where('conversation_list.0.id', $assignedOnA->id)
        );
});

test('AI视图结合并带负责人未分配缩小AI会话到未分配只', function () {
    $contactA = Contact::factory()->create([]);
    $contactB = Contact::factory()->create([]);

    $unassignedAi = inboxThreadConversationFactory($contactA)
        ->create([
            'assigned_user_id' => null,
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::AiHandling,
        ]);

    inboxThreadConversationFactory($contactB)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::AiHandling,
        ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=ai&assignee=unassigned')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_view', 'ai')
            ->where('current_assignee', 'unassigned')
            ->has('conversation_list', 1)
            ->where('conversation_list.0.id', $unassignedAi->id)
        );
});

test('已关闭视图并带显式负责人筛选严格按assigned_user_id和忽略谁已关闭它', function () {
    $contact = Contact::factory()->create([]);
    $teammate = User::factory()->create();
    $this->attachInstance($teammate, $this->instance);

    $closedAssignedToTeammate = inboxThreadConversationFactory($contact)
        ->assignedTo($teammate)
        ->closed()
        ->create([]);

    inboxThreadConversationFactory($contact)
        ->assignedTo($this->user)
        ->closed()
        ->create([]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=closed&assignee='.$teammate->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_view', 'closed')
            ->where('current_assignee', $teammate->id)
            ->has('conversation_list', 1)
            ->where('conversation_list.0.id', $closedAssignedToTeammate->id)
        );
});

test('会话列表将本人会话的冗余未读访客消息数报告为unread_count', function () {
    $contact = Contact::factory()->create([]);

    $conversation = inboxThreadConversationFactory($contact)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
            'unread_visitor_message_count' => 2,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'first visitor message',
        'created_at' => now()->subMinutes(10),
        'updated_at' => now()->subMinutes(10),
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => 'teammate replied',
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'follow up question',
        'created_at' => now()->subMinutes(2),
        'updated_at' => now()->subMinutes(2),
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'and another one',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=mine')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('current_view', 'mine')
            ->has('conversation_list', 1)
            ->where('conversation_list.0.id', $conversation->id)
            ->where('conversation_list.0.unread_count', 2)
        );
});

test('操作员最后回复后会话列表unread_count为零', function () {
    $contact = Contact::factory()->create([]);

    $conversation = inboxThreadConversationFactory($contact)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
            'waiting_for_visitor_reply' => true,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'visitor asked',
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => 'teammate replied last',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=mine')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('conversation_list', 1)
            ->where('conversation_list.0.id', $conversation->id)
            ->where('conversation_list.0.unread_count', 0)
        );
});

test('非本人待接待会话不显示unread_count', function () {
    $contact = Contact::factory()->create([]);

    $conversation = inboxThreadConversationFactory($contact)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
            'assigned_user_id' => null,
            'unread_visitor_message_count' => 3,
        ]);

    foreach (range(1, 3) as $i) {
        ConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::Visitor,
            'kind' => MessageKind::Text,
            'content' => 'visitor message #'.$i,
            'created_at' => now()->subMinutes(10 - $i),
            'updated_at' => now()->subMinutes(10 - $i),
        ]);
    }

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=pending')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('conversation_list', 1)
            ->where('conversation_list.0.id', $conversation->id)
            ->where('conversation_list.0.unread_count', 0)
        );
});

test('打开会话会按当前客服清空unread_count且新访客消息会重新计数', function () {
    $contact = Contact::factory()->create([]);

    $conversation = inboxThreadConversationFactory($contact)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
            'unread_visitor_message_count' => 2,
        ]);

    foreach (range(1, 2) as $i) {
        ConversationMessage::query()->create([
            'conversation_id' => $conversation->id,
            'role' => MessageRole::Visitor,
            'kind' => MessageKind::Text,
            'content' => 'visitor unread #'.$i,
            'created_at' => now()->subMinutes(10 - $i),
            'updated_at' => now()->subMinutes(10 - $i),
        ]);
    }

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=mine')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversation_list.0.id', $conversation->id)
            ->where('conversation_list.0.unread_count', 2)
            ->where('tab_counts.mine', 1)
        );

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$conversation->id.'/read')
        ->assertNoContent();

    expect($conversation->fresh()->unread_visitor_message_count)->toBe(0);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=mine')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversation_list.0.id', $conversation->id)
            ->where('conversation_list.0.unread_count', 0)
            ->where('tab_counts.mine', 0)
        );

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'new visitor message after read',
        'created_at' => now()->addSecond(),
        'updated_at' => now()->addSecond(),
    ]);

    $conversation->update(['unread_visitor_message_count' => 1]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=mine')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversation_list.0.id', $conversation->id)
            ->where('conversation_list.0.unread_count', 1)
            ->where('tab_counts.mine', 1)
        );
});

test('点击非本人会话不会更新已读位置', function () {
    $contact = Contact::factory()->create([]);
    $teammate = User::factory()->create();
    $this->attachInstance($teammate, $this->instance);

    $conversation = inboxThreadConversationFactory($contact)
        ->assignedTo($teammate)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
            'unread_visitor_message_count' => 1,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'visitor message for colleague',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox?view=teammates')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('conversation_list.0.id', $conversation->id)
            ->where('conversation_list.0.unread_count', 0)
        );

    $this->actingAs($this->user)
        ->post('/app'.'/inbox/'.$conversation->id.'/read')
        ->assertNoContent();

    expect($conversation->fresh()->unread_visitor_message_count)->toBe(1);
});

test('tab_counts.pending会统计打开和teammate_pending会话且不受未读状态影响', function () {
    $contact = Contact::factory()->create([]);

    inboxThreadConversationFactory($contact)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
            'assigned_user_id' => null,
        ]);

    inboxThreadConversationFactory($contact)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
            'assigned_user_id' => null,
        ]);

    inboxThreadConversationFactory($contact)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
            'unread_visitor_message_count' => 1,
        ]);

    inboxThreadConversationFactory($contact)
        ->closed()
        ->create([
            'inbox_status' => ConversationInboxStatus::TeammatePending,
        ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab_counts.pending', 2)
        );
});

test('tab_counts.mine只统计分配给当前用户且有连续访客消息的打开会话', function () {
    $contact = Contact::factory()->create([]);
    $teammate = User::factory()->create();
    $this->attachInstance($teammate, $this->instance);

    $mineWithBacklog = inboxThreadConversationFactory($contact)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
            'unread_visitor_message_count' => 1,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $mineWithBacklog->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => 'first teammate reply',
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $mineWithBacklog->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'visitor follow up after my reply',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    $mineNoBacklog = inboxThreadConversationFactory($contact)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
            'waiting_for_visitor_reply' => true,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $mineNoBacklog->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'visitor asked',
        'created_at' => now()->subMinutes(10),
        'updated_at' => now()->subMinutes(10),
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $mineNoBacklog->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => 'I replied last',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    $otherTeammateBacklog = inboxThreadConversationFactory($contact)
        ->assignedTo($teammate)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
            'unread_visitor_message_count' => 1,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $otherTeammateBacklog->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'visitor still waiting',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    $closedMine = inboxThreadConversationFactory($contact)
        ->assignedTo($this->user)
        ->closed()
        ->create([
            'unread_visitor_message_count' => 1,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $closedMine->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'visitor message on closed conversation',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab_counts.mine', 1)
        );
});

test('tab_counts.teammates不统计同事会话未读消息', function () {
    $contact = Contact::factory()->create([]);
    $teammate = User::factory()->create();
    $this->attachInstance($teammate, $this->instance);

    $teammateWithBacklog = inboxThreadConversationFactory($contact)
        ->assignedTo($teammate)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
            'unread_visitor_message_count' => 1,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $teammateWithBacklog->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => 'colleague replied first',
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $teammateWithBacklog->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'visitor follow up after colleague reply',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    $teammateNoBacklog = inboxThreadConversationFactory($contact)
        ->assignedTo($teammate)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $teammateNoBacklog->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'visitor asked colleague',
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $teammateNoBacklog->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => 'colleague answered last',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    inboxThreadConversationFactory($contact)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    inboxThreadConversationFactory($contact)
        ->assignedTo($teammate)
        ->closed()
        ->create([]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab_counts.teammates', 0)
        );
});

test('tab_counts.ai不统计非本人会话未读消息', function () {
    $contact = Contact::factory()->create([]);

    $aiWithBacklog = inboxThreadConversationFactory($contact)
        ->create([
            'assigned_user_id' => null,
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::AiHandling,
            'unread_visitor_message_count' => 1,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $aiWithBacklog->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'visitor question for AI',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    $visitorWaitingWithBacklog = inboxThreadConversationFactory($contact)
        ->create([
            'assigned_user_id' => null,
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::AiHandling,
            'unread_visitor_message_count' => 1,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $visitorWaitingWithBacklog->id,
        'role' => MessageRole::Ai,
        'kind' => MessageKind::Text,
        'content' => 'AI replied first',
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $visitorWaitingWithBacklog->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'visitor came back',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    $aiNoBacklog = inboxThreadConversationFactory($contact)
        ->create([
            'assigned_user_id' => null,
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::AiHandling,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $aiNoBacklog->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'visitor asked',
        'created_at' => now()->subMinutes(5),
        'updated_at' => now()->subMinutes(5),
    ]);

    ConversationMessage::query()->create([
        'conversation_id' => $aiNoBacklog->id,
        'role' => MessageRole::Ai,
        'kind' => MessageKind::Text,
        'content' => 'AI answered last',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    $assignedAi = inboxThreadConversationFactory($contact)
        ->assignedTo($this->user)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::AiHandling,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $assignedAi->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'should not count toward ai tab',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    $teammatePending = inboxThreadConversationFactory($contact)
        ->create([
            'assigned_user_id' => null,
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
            'unread_visitor_message_count' => 1,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $teammatePending->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => 'should not count toward ai tab either',
        'created_at' => now()->subMinute(),
        'updated_at' => now()->subMinute(),
    ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab_counts.ai', 0)
        );
});

test('tab_counts统计当前收件箱状态', function () {
    $contact = Contact::factory()->create([]);
    inboxThreadConversationFactory($contact)
        ->create([
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
            'assigned_user_id' => null,
        ]);

    $this->actingAs($this->user)
        ->get('/app'.'/inbox')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab_counts.pending', 1)
            ->where('tab_counts.ai', 0)
            ->where('tab_counts.mine', 0)
            ->where('tab_counts.teammates', 0)
        );
});

test('partial reload 仅请求 current_search 时不执行会话列表与时间线查询', function () {
    $contact = Contact::factory()->create([]);
    inboxThreadConversationFactory($contact)->create([
        'status' => ConversationStatus::Open,
        'inbox_status' => ConversationInboxStatus::TeammatePending,
    ]);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $this->actingAs($this->user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => 'Inbox',
            'X-Inertia-Partial-Data' => 'current_search',
        ])
        ->get('/app'.'/inbox?search=refund')
        ->assertOk()
        ->assertJsonPath('props.current_search', 'refund');

    // 懒加载契约：未被 only 请求的重 prop（列表 / 选中会话时间线 / tab 计数）完全不执行查询。
    $heavyQueries = array_values(array_filter(
        $queries,
        fn (string $sql) => str_contains($sql, 'conversations') || str_contains($sql, 'conversation_messages'),
    ));

    expect($heavyQueries)->toBe([]);
});
