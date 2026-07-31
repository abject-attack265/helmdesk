<?php

use App\Actions\Reception\AppendAiMessageAction;
use App\Actions\Reception\AppendVisitorMessageAction;
use App\Actions\Reception\ReleaseConversationToAiAction;
use App\Actions\Reception\RequestHandoffAction;
use App\Actions\Reception\ResolveReceptionContextAction;
use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Data\Reception\ReceptionStateData;
use App\Enums\AiModelPurpose;
use App\Enums\ConversationEntryMode;
use App\Enums\ConversationEventType;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Enums\ReceptionRoutingMode;
use App\Enums\UserOnlineStatus;
use App\Enums\WebChannelVisitorIdentityMode;
use App\Events\Reception\InstanceReceptionUpdated;
use App\Events\Reception\VisitorConversationUpdated;
use App\Exceptions\BusinessException;
use App\Jobs\Reception\RunReceptionTurnJob;
use App\Models\Attachment;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\ConversationMessage;
use App\Models\Membership;
use App\Models\ReceptionPlan;
use App\Models\ReceptionPlanVersion;
use App\Models\User;
use App\Services\Reception\ChannelActivePlanVersionResolver;
use App\Services\Reception\ReceptionStateBuilder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

uses(RefreshDatabase::class);

/**
 * 为接待业务测试构造已解析的访客会话状态。
 *
 * @param  array<string, string>|null  $queryParams
 * @param  array<string, mixed>|null  $visitorClient
 */
function startReceptionTestSession(
    string $channelCode,
    ?string $sessionToken,
    ?ConversationEntryMode $entryMode = null,
    ?array $visitorEnvironment = null,
    ?string $userToken = null,
    ?array $queryParams = null,
    ?array $visitorClient = null,
): ReceptionStateData {
    $context = app(ResolveReceptionContextAction::class)->handle(
        $channelCode,
        $sessionToken,
        $entryMode,
        $visitorEnvironment,
        $userToken,
        $queryParams,
        $visitorClient,
    );

    return ReceptionStateBuilder::build(
        $context['channel'],
        $context['conversation'],
        $context['session_token'],
    );
}

/**
 * 创建全局接待和后台任务用途的 LLM 模型。
 */
function createReceptionModels(bool $isActive = true): void
{
    $provider = makeUsableAiProvider();

    makeAiModel(AiModelPurpose::ReceptionChat, $provider, $isActive);
    makeAiModel(AiModelPurpose::BackgroundTask, $provider, $isActive);
}

/**
 * 创建包含已部署接待方案和在线成员的测试渠道。
 */
function createReceptionChannel(
    ?string $personaDisplayName = null,
    array $channelAttributes = [],
    bool $aiAvailable = true,
    array $versionAttributes = [],
): Channel {
    createSystemSettings();
    createReceptionModels($aiAvailable);
    $plan = ReceptionPlan::factory()->create([
        'name' => '接待方案-'.Str::lower(Str::random(6)),
    ]);
    $baseSnapshot = ReceptionPlanVersion::factory()->definition()['snapshot_config'];

    if (filled($personaDisplayName)) {
        $baseSnapshot['persona_config'] = array_merge(
            $baseSnapshot['persona_config'],
            ['display_name' => $personaDisplayName],
        );
    }

    $versionAttributes['snapshot_config'] = array_replace_recursive(
        $baseSnapshot,
        $versionAttributes['snapshot_config'] ?? [],
    );

    $version = ReceptionPlanVersion::factory()
        ->for($plan, 'plan')
        ->create($versionAttributes);

    $channel = Channel::factory()->create(array_merge([
        'reception_plan_id' => $plan->id,
    ], $channelAttributes));

    Membership::query()->create([
        'user_id' => User::factory()->create()->id,
        'online_status' => UserOnlineStatus::Online->value,
    ]);

    return $channel;
}

/**
 * 返回渠道当前部署的接待方案版本 ID。
 */
function receptionChannelVersionId(Channel $channel): string
{
    return (string) app(ChannelActivePlanVersionResolver::class)->currentVersionForChannel($channel)->id;
}

/**
 * 构造接待方案快照中的流程策略配置。
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function receptionStrategyConfig(array $overrides): array
{
    return array_replace_recursive([
        'reception_mode' => ReceptionRoutingMode::AiFirst->value,
        'unassigned_ai_takeover_enabled' => false,
        'unassigned_ai_takeover_timeout_seconds' => 120,
        'teammate_no_response_ai_takeover_enabled' => true,
        'teammate_no_response_ai_takeover_timeout_seconds' => 300,
        'auto_close_enabled' => true,
        'auto_close_idle_minutes' => 10,
        'important_contact_ai_careful_reply_enabled' => true,
        'important_contact_ai_handoff_hint_enabled' => true,
        'important_contact_human_first_when_online_enabled' => false,
        'quote_visitor_message_enabled' => false,
        'handoff_available_notice' => '已为您转接人工客服，请稍等。',
        'handoff_no_teammate_notice' => '当前暂无法转接人工，我会继续为您处理。',
        'ai_unavailable_notice' => '很抱歉，AI 助手暂时无法为您服务，正在为您转接人工客服，请稍候。',
        'business_hours' => null,
    ], $overrides);
}

test('新会话会锁定渠道当前部署的接待方案版本', function () {
    $channel = createReceptionChannel();
    $initialVersion = ReceptionPlanVersion::query()->findOrFail(receptionChannelVersionId($channel));

    startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Standalone,
    );

    $conversation = Conversation::query()->firstOrFail();

    expect($conversation->reception_plan_version_id)->toBe($initialVersion->id);

    ReceptionPlanVersion::factory()
        ->for($initialVersion->plan, 'plan')
        ->create(['version_number' => 2]);

    $conversation->refresh();

    expect($conversation->reception_plan_version_id)->toBe($initialVersion->id);
});

test('暂停（软删除）的渠道拒绝新建访客会话', function () {
    $channel = createReceptionChannel();
    $channel->delete();

    expect(fn () => startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Standalone,
    ))->toThrow(GoneHttpException::class);

    expect(Conversation::query()->count())->toBe(0);
});

test('暂停的渠道仍允许已有会话继续消息往返', function () {
    $channel = createReceptionChannel();

    // 先在渠道仍在线时建立会话和拿到 session token
    $started = startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Standalone,
    );
    $sessionToken = $started->session_token;

    // 然后才暂停渠道
    $channel->delete();

    // 用同一个 session token resume 应仍能拿到原会话
    $resumed = startReceptionTestSession(
        $channel->code,
        $sessionToken,
        ConversationEntryMode::Standalone,
    );

    expect($resumed->session_token)->toBe($sessionToken)
        ->and($resumed->conversation_id)->toBe($started->conversation_id);

    // 仍可向已有会话追加访客消息
    $afterAppend = app(AppendVisitorMessageAction::class)->handle(
        $channel->code,
        $sessionToken,
        '渠道暂停后我还想继续聊',
    );

    expect($afterAppend->conversation_id)->toBe($started->conversation_id);
    expect(ConversationMessage::query()
        ->where('conversation_id', $started->conversation_id)
        ->where('content', '渠道暂停后我还想继续聊')
        ->exists())->toBeTrue();
});

test('启动接待时会在联系人上记录访客环境', function () {
    $channel = createReceptionChannel();

    $state = startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Standalone,
        [
            'locale' => 'en-US',
            'timezone' => 'America/New_York',
            'country' => 'US',
            'city' => 'New York',
        ],
    );

    $contact = Contact::query()->firstOrFail();

    expect($state->session_token)->not->toBeEmpty()
        ->and($contact->locale)->toBeNull()
        ->and($contact->timezone)->toBe('America/New_York')
        ->and($contact->country)->toBe('US')
        ->and($contact->city)->toBe('New York')
        ->and($contact->last_seen_at)->not->toBeNull();
});

test('启动接待接受废弃 IANA 时区别名（如越南 Asia/Saigon）', function () {
    // 部分地区浏览器仍上报已废弃别名，校验默认列表不含它会抛 422 让访客打不开聊天。
    $channel = createReceptionChannel();

    startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Standalone,
        ['timezone' => 'Asia/Saigon'],
    );

    expect(Contact::query()->firstOrFail()->timezone)->toBe('Asia/Saigon');
});

test('人工接管的会话追加访客消息后仍保持人工待接管状态', function () {
    $channel = createReceptionChannel();

    $started = startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Standalone,
    );

    $teammate = User::factory()->create();
    Conversation::query()->firstOrFail()->forceFill([
        'assigned_user_id' => $teammate->id,
        'inbox_status' => ConversationInboxStatus::TeammateHandling,
    ])->save();

    $state = app(AppendVisitorMessageAction::class)->handle(
        $channel->code,
        $started->session_token,
        'hello after teammate claimed',
        ConversationEntryMode::Standalone,
    );

    $conversation = Conversation::query()->findOrFail($state->conversation_id);

    expect($conversation->inbox_status)
        ->toBe(ConversationInboxStatus::TeammateHandling)
        ->and($state->messages[0]->content)
        ->toBe('hello after teammate claimed');
});

test('访客端只展示消息正文', function () {
    $channel = createReceptionChannel();
    $contact = Contact::factory()->create([
        'locale' => 'en',
    ]);
    $conversation = Conversation::factory()
        ->forContact($contact)
        ->for($channel)
        ->create([
            'visitor_locale' => 'en',
        ]);
    ConversationMessage::factory()->forConversation($conversation)->create([
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => '你好',
        'payload' => null,
    ]);

    $state = ReceptionStateBuilder::build($channel, $conversation, 'session-token');

    expect($state->messages[0]->content)->toBe('你好');
});

test('接待会话记录组件入口模式', function () {
    $channel = createReceptionChannel();

    $started = startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Widget,
    );

    $state = app(AppendVisitorMessageAction::class)->handle(
        $channel->code,
        $started->session_token,
        'hello from widget',
        ConversationEntryMode::Widget,
    );

    $conversation = Conversation::query()->findOrFail($state->conversation_id);

    expect($conversation->entry_mode)
        ->toBe(ConversationEntryMode::Widget)
        ->and($state->messages[0]->content)
        ->toBe('hello from widget');
});

test('访客消息追加拒绝无效时区', function () {
    $channel = createReceptionChannel();
    $started = startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Standalone,
        ['timezone' => 'Asia/Shanghai'],
    );

    app(AppendVisitorMessageAction::class)->handle(
        $channel->code,
        $started->session_token,
        'hello from visitor',
        ConversationEntryMode::Standalone,
        ['timezone' => 'Invalid/Timezone'],
        [],
    );
})->throws(ValidationException::class);

test('访客消息刷新拒绝无效时区', function () {
    $channel = createReceptionChannel();
    $started = startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Standalone,
        ['timezone' => 'Asia/Shanghai'],
    );
    $contact = Contact::query()->firstOrFail();
    $contact->forceFill(['last_seen_at' => now()->subHour()])->saveQuietly();
    $previousLastSeen = $contact->last_seen_at->copy();

    expect(fn () => app(AppendVisitorMessageAction::class)->handle(
        $channel->code,
        $started->session_token,
        'hello',
        ConversationEntryMode::Standalone,
        ['timezone' => 'Not/A_Timezone'],
    ))->toThrow(ValidationException::class);

    $contact->refresh();

    expect($contact->timezone)->toBe('Asia/Shanghai')
        ->and($contact->last_seen_at->equalTo($previousLastSeen))->toBeTrue();
});

test('访客回复等待标记会由AI回复设置并由访客消息清除', function () {
    $channel = createReceptionChannel();
    $started = startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Standalone,
    );

    app(AppendVisitorMessageAction::class)->handle(
        $channel->code,
        $started->session_token,
        'hello',
    );

    expect(Conversation::query()->firstOrFail()->unread_visitor_message_count)->toBe(1);

    $aiState = app(AppendAiMessageAction::class)->handle(
        Conversation::query()->firstOrFail(),
        'hello, how can I help?',
    );

    expect(Conversation::query()->firstOrFail()->waiting_for_visitor_reply)->toBeTrue()
        ->and(Conversation::query()->firstOrFail()->unread_visitor_message_count)->toBe(0)
        ->and($aiState->toArray())->not->toHaveKey('inbox_status')
        ->and($aiState->toArray())->not->toHaveKey('inbox_status_label')
        ->and($aiState->toArray())->not->toHaveKey('waiting_for_visitor_reply')
        ->and($aiState->toArray())->not->toHaveKey('waiting_for_visitor_reply_label');

    $visitorState = app(AppendVisitorMessageAction::class)->handle(
        $channel->code,
        $started->session_token,
        'I have a question',
    );

    expect(Conversation::query()->firstOrFail()->waiting_for_visitor_reply)->toBeFalse()
        ->and(Conversation::query()->firstOrFail()->unread_visitor_message_count)->toBe(1)
        ->and($visitorState->toArray())->not->toHaveKey('inbox_status')
        ->and($visitorState->toArray())->not->toHaveKey('inbox_status_label')
        ->and($visitorState->toArray())->not->toHaveKey('waiting_for_visitor_reply')
        ->and($visitorState->toArray())->not->toHaveKey('waiting_for_visitor_reply_label');
});

test('访客可以发送附件只图片消息和下载保持在会话范围内', function () {
    fakeAttachmentStorage();

    $channel = createReceptionChannel();
    $started = startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Standalone,
    );

    $attachment = Attachment::factory()->create([
        'object_key' => 'attachments/conversation_image/photo.png',
        'original_name' => 'photo.png',
        'mime_type' => 'image/png',
        'extension' => 'png',
        'byte_size' => 10,
        'purpose' => 'conversation_image',
        'status' => 'uploaded',
        'session_token_hash' => hash('sha256', $started->session_token),
    ]);
    $attachment->filesystem()->put($attachment->object_key, 'fake-image');

    $state = app(AppendVisitorMessageAction::class)->handle(
        $channel->code,
        $started->session_token,
        '',
        ConversationEntryMode::Standalone,
        [],
        [(string) $attachment->id],
    );

    $message = ConversationMessage::query()->firstOrFail();

    expect($message->kind)->toBe(MessageKind::Image)
        ->and($message->content)->toBeNull()
        ->and($message->attachments()->count())->toBe(1)
        ->and($state->messages[0]->attachments[0]->id)->toBe((string) $attachment->id)
        ->and($state->messages[0]->attachments[0]->url)
        ->toBe(route('attachments.content', ['attachment' => $attachment->id]));
});

test('访客附件上传保持在会话范围内当浏览器已认证时', function () {
    fakeAttachmentStorage();

    $channel = createReceptionChannel();
    $started = startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Standalone,
    );
    $user = User::factory()->create();
    $contents = 'hello attachment';

    $presign = $this->actingAs($user)
        ->withHeader('X-Helmdesk-Visitor-Token', $started->session_token)
        ->postJson('/api/visitor/attachments/presign', [
            'purpose' => 'conversation_file',
            'file_name' => 'note.txt',
            'mime_type' => 'text/plain',
            'byte_size' => strlen($contents),
            'context' => [
                'channel_code' => $channel->code,
                'conversation_id' => $started->conversation_id,
                'session_token' => $started->session_token,
            ],
        ])
        ->assertOk();

    $attachmentId = $presign->json('attachment_id');
    $attachment = Attachment::query()->findOrFail($attachmentId);
    $attachment->filesystem()->put($attachment->object_key, $contents);

    $this->actingAs($user)
        ->withHeader('X-Helmdesk-Visitor-Token', $started->session_token)
        ->postJson('/api/visitor/attachments/'.$attachmentId.'/finalize')
        ->assertOk()
        ->assertJsonPath('attachment.status', 'uploaded');

    $attachment = Attachment::query()->findOrFail($attachmentId);

    expect($attachment->uploaded_by_user_id)->toBeNull()
        ->and($attachment->session_token_hash)->toBe(hash('sha256', $started->session_token));

    $state = app(AppendVisitorMessageAction::class)->handle(
        $channel->code,
        $started->session_token,
        '',
        ConversationEntryMode::Standalone,
        [],
        [$attachmentId],
    );
    $message = ConversationMessage::query()->firstOrFail();

    expect($message->kind)->toBe(MessageKind::File)
        ->and($message->attachments()->count())->toBe(1)
        ->and($state->messages[0]->attachments[0]->id)->toBe($attachmentId);
});

test('访客可以一次发送多附件并按 B 端规则拆成独立消息', function () {
    fakeAttachmentStorage();

    $channel = createReceptionChannel();
    $started = startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Standalone,
    );

    $attachmentIds = collect(range(1, 3))
        ->map(function (int $index) use ($started): string {
            $key = 'attachments/conversation_image/photo'.$index.'.png';

            $attachment = Attachment::factory()->create([
                'object_key' => $key,
                'original_name' => 'photo'.$index.'.png',
                'mime_type' => 'image/png',
                'extension' => 'png',
                'byte_size' => 10,
                'purpose' => 'conversation_image',
                'status' => 'uploaded',
                'session_token_hash' => hash('sha256', $started->session_token),
            ]);
            $attachment->filesystem()->put($attachment->object_key, 'fake-image');

            return (string) $attachment->id;
        })
        ->all();

    $state = app(AppendVisitorMessageAction::class)->handle(
        $channel->code,
        $started->session_token,
        '附件来啦',
        ConversationEntryMode::Standalone,
        [],
        $attachmentIds,
    );

    $messages = ConversationMessage::query()
        ->where('conversation_id', $state->conversation_id)
        ->orderBy('created_at')
        ->orderBy('id')
        ->get();

    expect($messages)->toHaveCount(4)
        ->and($messages[0]->kind)->toBe(MessageKind::Text)
        ->and($messages[0]->content)->toBe('附件来啦')
        ->and($messages[0]->attachments()->count())->toBe(0)
        ->and($messages[1]->kind)->toBe(MessageKind::Image)
        ->and($messages[1]->content)->toBeNull()
        ->and($messages[1]->attachments()->count())->toBe(1)
        ->and($messages[2]->attachments()->count())->toBe(1)
        ->and($messages[3]->attachments()->count())->toBe(1)
        ->and(collect($state->messages)->map(fn ($message) => $message->content)->all())
        ->toBe(['附件来啦', '', '', '']);
});

test('访客一次发送附件超过上限会被拒绝', function () {
    $channel = createReceptionChannel();
    $started = startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Standalone,
    );

    $attachmentIds = array_fill(0, 11, '01jxxxxxxxxxxxxxxxxxxxxxxx');

    expect(fn () => app(AppendVisitorMessageAction::class)->handle(
        $channel->code,
        $started->session_token,
        'too many',
        ConversationEntryMode::Standalone,
        [],
        $attachmentIds,
    ))->toThrow(ValidationException::class);
});

test('接待状态包含之前已关闭会话消息用于同一联系人频道', function () {
    $channel = createReceptionChannel();
    $started = startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Standalone,
    );

    $firstConversation = Conversation::query()->findOrFail($started->conversation_id);
    ConversationMessage::factory()->visitorText()->forConversation($firstConversation)->create([
        'content' => 'old visitor question',
        'created_at' => now()->subMinutes(20),
        'updated_at' => now()->subMinutes(20),
    ]);
    $firstConversation->update([
        'status' => ConversationStatus::Closed,
        'closed_at' => now()->subMinutes(10),
    ]);

    $otherChannel = Channel::factory()->create([
    ]);
    $otherChannelConversation = Conversation::factory()
        ->forContact($firstConversation->contact)
        ->create([
            'channel_id' => $otherChannel->id,
        ]);
    ConversationMessage::factory()->visitorText()->forConversation($otherChannelConversation)->create([
        'content' => 'other channel question',
        'created_at' => now()->subMinutes(15),
        'updated_at' => now()->subMinutes(15),
    ]);

    $secondStarted = startReceptionTestSession(
        $channel->code,
        $started->session_token,
        ConversationEntryMode::Standalone,
    );
    $secondConversation = Conversation::query()->findOrFail($secondStarted->conversation_id);
    ConversationMessage::factory()->visitorText()->forConversation($secondConversation)->create([
        'content' => 'current visitor question',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $state = startReceptionTestSession(
        $channel->code,
        $started->session_token,
        ConversationEntryMode::Standalone,
    );

    expect($state->conversation_id)->toBe($secondConversation->id)
        ->and(collect($state->messages)->map(fn ($message) => $message->content)->all())
        ->toBe(['old visitor question', 'current visitor question']);
});

test('人工可用时转交请求进入共享队列且不分配同事', function () {
    $channel = createReceptionChannel();
    $started = startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Standalone,
    );
    app(AppendVisitorMessageAction::class)->handle(
        $channel->code,
        $started->session_token,
        '请转人工',
    );
    $visitorMessage = ConversationMessage::query()
        ->where('conversation_id', $started->conversation_id)
        ->where('role', MessageRole::Visitor)
        ->firstOrFail();

    $decision = app(RequestHandoffAction::class)->handle(
        Conversation::query()->firstOrFail(),
        'needs_human',
        (string) $visitorMessage->id,
    );

    $conversation = Conversation::query()->firstOrFail();
    $notice = __('reception.defaults.handoff_available_notice');

    expect($decision->accepted)->toBeTrue()
        ->and($decision->notice)->toBe($notice)
        ->and($conversation->inbox_status)->toBe(ConversationInboxStatus::TeammatePending)
        ->and($conversation->assigned_user_id)->toBeNull()
        ->and((string) ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', MessageRole::Ai)
            ->where('content', $notice)
            ->firstOrFail()->quoted_message_id)->toBe((string) $visitorMessage->id)
        ->and(ConversationEvent::query()->where('conversation_id', $conversation->id)->where('type', 'handoff_requested')->exists())->toBeTrue();

    // reason 由 AI 自由生成，未知值（needs_human）在入口归一化为 ai_requested，确保落库与返回值都干净。
    $handoffEvent = ConversationEvent::query()
        ->where('conversation_id', $conversation->id)
        ->where('type', 'handoff_requested')
        ->firstOrFail();
    expect($decision->reason)->toBe('ai_requested')
        ->and($handoffEvent->payload['reason'])->toBe('ai_requested');
});

test('同事第一个接待开始于同事队列且会话锁定接待方案版本', function () {
    $channel = createReceptionChannel(null, versionAttributes: [
        'snapshot_config' => [
            'strategy_config' => receptionStrategyConfig([
                'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
                'unassigned_ai_takeover_enabled' => false,
            ]),
        ],
    ]);

    startReceptionTestSession($channel->code, null, ConversationEntryMode::Standalone);

    $conversation = Conversation::query()->firstOrFail();

    expect($conversation->reception_plan_version_id)->toBe(receptionChannelVersionId($channel))
        ->and($conversation->inbox_status)->toBe(ConversationInboxStatus::TeammatePending);
});

test('同事优先排队且未启用未分配接管时保持人工待接', function () {
    $channel = createReceptionChannel(null, versionAttributes: [
        'snapshot_config' => [
            'strategy_config' => receptionStrategyConfig([
                'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
                'unassigned_ai_takeover_enabled' => false,
            ]),
        ],
    ]);

    $started = startReceptionTestSession($channel->code, null, ConversationEntryMode::Standalone);

    expect(Conversation::query()->firstOrFail()->inbox_status)->toBe(ConversationInboxStatus::TeammatePending);

    app(AppendAiMessageAction::class)->handle(Conversation::query()->firstOrFail(), 'hello');
})->throws(BusinessException::class);

test('同事优先接待即使未配置未分配接管仍可手动释放给AI', function () {
    Event::fake([InstanceReceptionUpdated::class, VisitorConversationUpdated::class]);
    // 释放会派发补答 turn（最后一条是访客消息）；fake 队列避免 sync 驱动内联执行真实推理。
    Queue::fake();

    $channel = createReceptionChannel(null, versionAttributes: [
        'snapshot_config' => [
            'strategy_config' => receptionStrategyConfig([
                'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
                'unassigned_ai_takeover_enabled' => false,
            ]),
        ],
    ]);
    $user = User::factory()->create();
    $contact = Contact::factory()->create([]);
    $conversation = Conversation::factory()
        ->forContact($contact)
        ->assignedTo($user)
        ->create([
            'channel_id' => $channel->id,
            'reception_plan_version_id' => receptionChannelVersionId($channel),
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '请继续处理',
    ]);

    $released = app(ReleaseConversationToAiAction::class)->handle($conversation, $user);

    // 释放状态同步到坐席端和访客端，积压访客消息由 AI turn 异步补答。
    $broadcastEvent = fn (object $event): string => $event->payload['event'];
    $appEvents = collect(Event::dispatched(InstanceReceptionUpdated::class))
        ->map(fn (array $args): string => $broadcastEvent($args[0]))
        ->all();
    $visitorEvents = collect(Event::dispatched(VisitorConversationUpdated::class))
        ->map(fn (array $args): string => $broadcastEvent($args[0]))
        ->all();

    expect($released->assigned_user_id)->toBeNull()
        ->and($released->inbox_status)->toBe(ConversationInboxStatus::AiHandling)
        ->and($appEvents)->toBe(['conversation_released_to_ai'])
        ->and($visitorEvents)->toBe(['conversation_released_to_ai'])
        ->and(ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('role', MessageRole::Ai)
            ->exists())->toBeFalse();
});

test('排队中的会话任意坐席都可手动交给 AI 并补答积压消息', function () {
    Queue::fake();

    $channel = createReceptionChannel(null, versionAttributes: [
        'snapshot_config' => [
            'strategy_config' => receptionStrategyConfig([
                'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
            ]),
        ],
    ]);
    // 非会话负责人（排队中本就无人负责），验证任意坐席都可操作。
    $actor = User::factory()->create();
    $contact = Contact::factory()->create([]);
    $conversation = Conversation::factory()
        ->forContact($contact)
        ->create([
            'channel_id' => $channel->id,
            'reception_plan_version_id' => receptionChannelVersionId($channel),
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
            'assigned_user_id' => null,
        ]);
    $question = ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '排队等了很久，帮我看看订单',
    ]);

    $released = app(ReleaseConversationToAiAction::class)->handle($conversation, $actor);

    expect($released->inbox_status)->toBe(ConversationInboxStatus::AiHandling)
        ->and($released->assigned_user_id)->toBeNull();

    // 补答 turn 带上排队期间积压的访客问题。
    Queue::assertPushed(
        RunReceptionTurnJob::class,
        fn (RunReceptionTurnJob $job): bool => $job->conversationId === (string) $conversation->id
            && $job->messageIds === [(string) $question->id],
    );
});

test('排队中的会话在 AI 不可用时手动交给 AI 会被明确拒绝', function () {
    $channel = createReceptionChannel(null, aiAvailable: false, versionAttributes: [
        'snapshot_config' => [
            'strategy_config' => receptionStrategyConfig([
                'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
            ]),
        ],
    ]);
    $actor = User::factory()->create();
    $contact = Contact::factory()->create([]);
    $conversation = Conversation::factory()
        ->forContact($contact)
        ->create([
            'channel_id' => $channel->id,
            'reception_plan_version_id' => receptionChannelVersionId($channel),
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammatePending,
            'assigned_user_id' => null,
        ]);

    expect(fn () => app(ReleaseConversationToAiAction::class)->handle($conversation, $actor))
        ->toThrow(BusinessException::class, __('conversation.errors.release_to_ai_unavailable'));

    expect($conversation->refresh()->inbox_status)->toBe(ConversationInboxStatus::TeammatePending);
});

test('同事优先接待会在配置超时后让 AI 接管', function () {
    $channel = createReceptionChannel(null, versionAttributes: [
        'snapshot_config' => [
            'strategy_config' => receptionStrategyConfig([
                'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
                'unassigned_ai_takeover_enabled' => true,
                'unassigned_ai_takeover_timeout_seconds' => 0,
            ]),
        ],
    ]);

    $started = startReceptionTestSession($channel->code, null, ConversationEntryMode::Standalone);

    expect(Conversation::query()->firstOrFail()->inbox_status)->toBe(ConversationInboxStatus::TeammatePending);

    startReceptionTestSession($channel->code, $started->session_token, ConversationEntryMode::Standalone);

    $conversation = Conversation::query()->firstOrFail();

    expect($conversation->reception_plan_version_id)->toBe(receptionChannelVersionId($channel))
        ->and($conversation->inbox_status)->toBe(ConversationInboxStatus::AiHandling);
});

test('人工优先会话在没有人工可接待时仍保持排队', function () {
    // 人工优先且未开启无人接待超时转 AI 时，无人在线也保持排队等待坐席。
    $channel = createReceptionChannel(null, versionAttributes: [
        'snapshot_config' => [
            'strategy_config' => receptionStrategyConfig([
                'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
                'unassigned_ai_takeover_enabled' => false,
            ]),
        ],
    ]);

    $started = startReceptionTestSession($channel->code, null, ConversationEntryMode::Standalone);

    expect(Conversation::query()->firstOrFail()->inbox_status)->toBe(ConversationInboxStatus::TeammatePending);

    $teammate = User::query()->whereHas('membership')->firstOrFail();
    Membership::query()->whereKey($teammate->id)->update([
        'online_status' => UserOnlineStatus::Offline->value,
    ]);

    startReceptionTestSession($channel->code, $started->session_token, ConversationEntryMode::Standalone);

    expect(Conversation::query()->firstOrFail()->inbox_status)->toBe(ConversationInboxStatus::TeammatePending);
});

test('人工优先会话进入非营业时间仍保持排队', function () {
    // 人工优先且未开启无人接待超时转 AI 时，非营业时间也保持排队。
    $schedule = [];
    for ($day = 1; $day <= 7; $day++) {
        $schedule[] = ['day' => $day, 'enabled' => true, 'open' => '09:00', 'close' => '18:00'];
    }

    try {
        Carbon::setTestNow('2026-05-26 10:00:00');

        $channel = createReceptionChannel(null, versionAttributes: [
            'snapshot_config' => [
                'strategy_config' => receptionStrategyConfig([
                    'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
                    'unassigned_ai_takeover_enabled' => false,
                    'business_hours' => [
                        'timezone' => 'UTC',
                        'outside_hours_notice' => '当前非人工服务时间，AI 将继续为您服务。',
                        'schedule' => $schedule,
                    ],
                ]),
            ],
        ]);

        $started = startReceptionTestSession($channel->code, null, ConversationEntryMode::Standalone);

        expect(Conversation::query()->firstOrFail()->inbox_status)->toBe(ConversationInboxStatus::TeammatePending);

        Carbon::setTestNow('2026-05-26 20:00:00');

        startReceptionTestSession($channel->code, $started->session_token, ConversationEntryMode::Standalone);

        expect(Conversation::query()->firstOrFail()->inbox_status)->toBe(ConversationInboxStatus::TeammatePending);
    } finally {
        Carbon::setTestNow();
    }
});

test('AI 优先接待会在同事未响应时让 AI 接管', function () {
    $channel = createReceptionChannel(null, versionAttributes: [
        'snapshot_config' => [
            'strategy_config' => receptionStrategyConfig([
                'reception_mode' => ReceptionRoutingMode::AiFirst->value,
                'teammate_no_response_ai_takeover_enabled' => true,
                'teammate_no_response_ai_takeover_timeout_seconds' => 0,
            ]),
        ],
    ]);
    $user = User::factory()->create();

    $started = startReceptionTestSession($channel->code, null, ConversationEntryMode::Standalone);
    $conversation = Conversation::query()->findOrFail($started->conversation_id);
    $conversation->update([
        'assigned_user_id' => $user->id,
        'inbox_status' => ConversationInboxStatus::TeammateHandling,
    ]);
    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '还在吗？',
        'created_at' => now()->subMinute(),
    ]);

    startReceptionTestSession($channel->code, $started->session_token, ConversationEntryMode::Standalone);

    $conversation->refresh();

    expect($conversation->assigned_user_id)->toBeNull()
        ->and($conversation->reception_plan_version_id)->toBe(receptionChannelVersionId($channel))
        ->and($conversation->inbox_status)->toBe(ConversationInboxStatus::AiHandling);
});

test('人工优先会话进入 AI 接待后在访客再次进入时维持当前接待方', function () {
    // AI 接手后（超时接管或手动交给 AI）持续服务到转人工为止，访客再进入不改变接待方。
    $channel = createReceptionChannel(null, versionAttributes: [
        'snapshot_config' => [
            'strategy_config' => receptionStrategyConfig([
                'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
                'unassigned_ai_takeover_enabled' => false,
            ]),
        ],
    ]);

    $started = startReceptionTestSession($channel->code, null, ConversationEntryMode::Standalone);
    $conversation = Conversation::query()->findOrFail($started->conversation_id);
    // 构造由超时接管或人工释放进入 AI 接待的已有会话。
    $conversation->update(['inbox_status' => ConversationInboxStatus::AiHandling]);

    startReceptionTestSession($channel->code, $started->session_token, ConversationEntryMode::Standalone);

    $conversation->refresh();

    expect($conversation->inbox_status)->toBe(ConversationInboxStatus::AiHandling);
});

test('AI 优先会话在访客再次进入时维持当前接待方', function () {
    $channel = createReceptionChannel(null, versionAttributes: [
        'snapshot_config' => [
            'strategy_config' => receptionStrategyConfig([
                'reception_mode' => ReceptionRoutingMode::AiFirst->value,
            ]),
        ],
    ]);

    $started = startReceptionTestSession($channel->code, null, ConversationEntryMode::Standalone);
    $conversation = Conversation::query()->findOrFail($started->conversation_id);

    expect($conversation->inbox_status)->toBe(ConversationInboxStatus::AiHandling);

    startReceptionTestSession($channel->code, $started->session_token, ConversationEntryMode::Standalone);

    $conversation->refresh();

    expect($conversation->inbox_status)->toBe(ConversationInboxStatus::AiHandling);
});

test('默认接待方案不会在客服无响应时让 AI 接管', function () {
    // 默认策略（defaultConfig）下 teammate_no_response_ai_takeover_enabled 为 false：
    // 客服接待后即便长时间未回复也保持人工接待，不被 AI 接管。
    $channel = createReceptionChannel();
    $teammate = User::query()->whereHas('membership')->firstOrFail();

    $started = startReceptionTestSession($channel->code, null, ConversationEntryMode::Standalone);
    $conversation = Conversation::query()->findOrFail($started->conversation_id);
    $conversation->update([
        'assigned_user_id' => $teammate->id,
        'inbox_status' => ConversationInboxStatus::TeammateHandling,
    ]);
    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '还在吗？',
        'created_at' => now()->subHour(),
    ]);

    startReceptionTestSession($channel->code, $started->session_token, ConversationEntryMode::Standalone);

    $conversation->refresh();

    expect((string) $conversation->assigned_user_id)->toBe((string) $teammate->id)
        ->and($conversation->inbox_status)->toBe(ConversationInboxStatus::TeammateHandling);
});

test('人工已接待会话在客服离线时保持人工接待', function () {
    $channel = createReceptionChannel(null, versionAttributes: [
        'snapshot_config' => [
            'strategy_config' => receptionStrategyConfig([
                'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
                'teammate_no_response_ai_takeover_enabled' => false,
            ]),
        ],
    ]);
    $teammate = User::query()->whereHas('membership')->firstOrFail();

    $started = startReceptionTestSession($channel->code, null, ConversationEntryMode::Standalone);
    $conversation = Conversation::query()->findOrFail($started->conversation_id);
    $conversation->update([
        'assigned_user_id' => $teammate->id,
        'inbox_status' => ConversationInboxStatus::TeammateHandling,
    ]);

    Membership::query()->whereKey($teammate->id)->update([
        'online_status' => UserOnlineStatus::Offline->value,
    ]);

    startReceptionTestSession($channel->code, $started->session_token, ConversationEntryMode::Standalone);

    $conversation->refresh();

    expect((string) $conversation->assigned_user_id)->toBe((string) $teammate->id)
        ->and($conversation->inbox_status)->toBe(ConversationInboxStatus::TeammateHandling);
});

test('人工已接待会话在非营业时间保持人工接待', function () {
    $schedule = [];
    for ($day = 1; $day <= 7; $day++) {
        $schedule[] = ['day' => $day, 'enabled' => false, 'open' => '09:00', 'close' => '18:00'];
    }

    $channel = createReceptionChannel(null, versionAttributes: [
        'snapshot_config' => [
            'strategy_config' => receptionStrategyConfig([
                'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
                'teammate_no_response_ai_takeover_enabled' => false,
                'business_hours' => [
                    'timezone' => 'UTC',
                    'outside_hours_notice' => '当前非人工服务时间，AI 将继续为您服务。',
                    'schedule' => $schedule,
                ],
            ]),
        ],
    ]);
    $teammate = User::query()->whereHas('membership')->firstOrFail();

    $started = startReceptionTestSession($channel->code, null, ConversationEntryMode::Standalone);
    $conversation = Conversation::query()->findOrFail($started->conversation_id);
    $conversation->update([
        'assigned_user_id' => $teammate->id,
        'inbox_status' => ConversationInboxStatus::TeammateHandling,
    ]);

    startReceptionTestSession($channel->code, $started->session_token, ConversationEntryMode::Standalone);

    $conversation->refresh();

    expect((string) $conversation->assigned_user_id)->toBe((string) $teammate->id)
        ->and($conversation->inbox_status)->toBe(ConversationInboxStatus::TeammateHandling);
});

test('AI 优先接待在接待模型不可用时进入人工待接', function () {
    $channel = createReceptionChannel(null, aiAvailable: false, versionAttributes: [
        'snapshot_config' => [
            'strategy_config' => receptionStrategyConfig([
                'reception_mode' => ReceptionRoutingMode::AiFirst->value,
            ]),
        ],
    ]);

    $started = startReceptionTestSession($channel->code, null, ConversationEntryMode::Standalone);

    $conversation = Conversation::query()->firstOrFail();

    expect($started->session_token)->not->toBeEmpty()
        ->and($conversation->inbox_status)->toBe(ConversationInboxStatus::TeammatePending);
});

test('同事优先未分配接管在接待模型不可用时保持人工待接', function () {
    $channel = createReceptionChannel(null, aiAvailable: false, versionAttributes: [
        'snapshot_config' => [
            'strategy_config' => receptionStrategyConfig([
                'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
                'unassigned_ai_takeover_enabled' => true,
                'unassigned_ai_takeover_timeout_seconds' => 0,
            ]),
        ],
    ]);

    $started = startReceptionTestSession($channel->code, null, ConversationEntryMode::Standalone);

    expect(Conversation::query()->firstOrFail()->inbox_status)->toBe(ConversationInboxStatus::TeammatePending);

    startReceptionTestSession($channel->code, $started->session_token, ConversationEntryMode::Standalone);

    expect(Conversation::query()->firstOrFail()->inbox_status)->toBe(ConversationInboxStatus::TeammatePending);
});

test('实际接待身份使用 persona 名称', function () {
    $user = User::factory()->create(['name' => '内部客服']);
    $channel = createReceptionChannel('AI 小助手', [
        'settings' => ChannelWebSettingsData::defaults([
            'visitor_interface' => [
                'visitor_identity_mode' => WebChannelVisitorIdentityMode::ActualReceptionist->value,
            ],
        ]),
    ]);
    Membership::query()->create([
        'user_id' => $user->id,
        'nickname' => '对外客服',
    ]);

    $started = startReceptionTestSession($channel->code, null, ConversationEntryMode::Standalone);
    $conversation = Conversation::query()->findOrFail($started->conversation_id);
    ConversationMessage::factory()->forConversation($conversation)->create([
        'role' => MessageRole::Ai,
        'kind' => MessageKind::Text,
        'content' => 'ai message',
    ]);
    ConversationMessage::factory()->forConversation($conversation)->create([
        'sender_user_id' => $user->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => 'teammate message',
    ]);

    $state = startReceptionTestSession($channel->code, $started->session_token, ConversationEntryMode::Standalone);
    $senders = collect($state->messages)->pluck('sender_name', 'content');

    expect($senders['ai message'])->toBe('AI 小助手')
        ->and($senders['teammate message'])->toBe('对外客服');

    Membership::query()->whereKey($user->id)->update(['nickname' => null]);

    $state = startReceptionTestSession($channel->code, $started->session_token, ConversationEntryMode::Standalone);
    $senders = collect($state->messages)->pluck('sender_name', 'content');

    expect($senders['ai message'])->toBe('AI 小助手')
        ->and($senders['teammate message'])->toBe('内部客服');
});

test('统一服务身份会隐藏实际 AI 和同事名称', function () {
    $user = User::factory()->create(['name' => '内部客服']);
    $channel = createReceptionChannel('AI 小助手', [
        'settings' => ChannelWebSettingsData::defaults([
            'visitor_interface' => [
                'visitor_identity_mode' => WebChannelVisitorIdentityMode::UnifiedService->value,
                'service_display_name' => '统一客服',
            ],
        ]),
    ]);
    Membership::query()->create([
        'user_id' => $user->id,
        'nickname' => '对外客服',
    ]);

    $started = startReceptionTestSession($channel->code, null, ConversationEntryMode::Standalone);
    $conversation = Conversation::query()->findOrFail($started->conversation_id);
    ConversationMessage::factory()->forConversation($conversation)->create([
        'role' => MessageRole::Ai,
        'kind' => MessageKind::Text,
        'content' => 'ai message',
    ]);
    ConversationMessage::factory()->forConversation($conversation)->create([
        'sender_user_id' => $user->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => 'teammate message',
    ]);

    $state = startReceptionTestSession($channel->code, $started->session_token, ConversationEntryMode::Standalone);
    $senders = collect($state->messages)->pluck('sender_name', 'content');

    expect($state->assistant_name)->toBe('统一客服')
        ->and($senders['ai message'])->toBe('统一客服')
        ->and($senders['teammate message'])->toBe('统一客服');
});

test('AI 不可用冷却期内保持人工待接状态', function () {
    $channel = createReceptionChannel(null, versionAttributes: [
        'snapshot_config' => [
            'strategy_config' => receptionStrategyConfig([
                'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
                'unassigned_ai_takeover_enabled' => true,
                'unassigned_ai_takeover_timeout_seconds' => 0,
            ]),
        ],
    ]);
    Membership::query()->delete();

    $started = startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Standalone,
    );

    $conversation = Conversation::query()->findOrFail($started->conversation_id);
    $conversation->update(['inbox_status' => ConversationInboxStatus::TeammatePending]);
    ConversationEvent::query()->create([
        'conversation_id' => $conversation->id,
        'type' => ConversationEventType::HandoffRequested,
        'payload' => ['reason' => 'ai_unavailable', 'actor_kind' => 'system'],
        'created_at' => now(),
    ]);

    startReceptionTestSession(
        $channel->code,
        $started->session_token,
        ConversationEntryMode::Standalone,
    );

    $conversation->refresh();

    expect($conversation->inbox_status)->toBe(ConversationInboxStatus::TeammatePending);
});

test('AI 不可用冷却期过期后重新进入 AI 接待', function () {
    $channel = createReceptionChannel(null, versionAttributes: [
        'snapshot_config' => [
            'strategy_config' => receptionStrategyConfig([
                'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
                'unassigned_ai_takeover_enabled' => true,
                'unassigned_ai_takeover_timeout_seconds' => 0,
            ]),
        ],
    ]);
    Membership::query()->delete();

    $started = startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Standalone,
    );

    $conversation = Conversation::query()->findOrFail($started->conversation_id);
    $conversation->update(['inbox_status' => ConversationInboxStatus::TeammatePending]);
    ConversationEvent::query()->create([
        'conversation_id' => $conversation->id,
        'type' => ConversationEventType::HandoffRequested,
        'payload' => ['reason' => 'ai_unavailable', 'actor_kind' => 'system'],
        'created_at' => now()->subSeconds(301),
    ]);

    startReceptionTestSession(
        $channel->code,
        $started->session_token,
        ConversationEntryMode::Standalone,
    );

    $conversation->refresh();

    expect($conversation->inbox_status)->toBe(ConversationInboxStatus::AiHandling);
});

test('AI 接待回复落库时盖上 turn_id，供反查调用日志', function () {
    $channel = createReceptionChannel('AI 小海');

    $started = startReceptionTestSession(
        $channel->code,
        null,
        ConversationEntryMode::Standalone,
    );

    $conversation = Conversation::query()->findOrFail($started->conversation_id);
    $turnId = (string) Str::uuid();

    AppendAiMessageAction::run($conversation, '晚上十点关门哦', null, null, $turnId);

    $message = ConversationMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('role', MessageRole::Ai)
        ->where('content', '晚上十点关门哦')
        ->firstOrFail();

    expect($message->turn_id)->toBe($turnId);
});
