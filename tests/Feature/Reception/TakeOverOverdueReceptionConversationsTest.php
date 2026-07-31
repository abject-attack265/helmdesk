<?php

use App\Actions\Reception\TakeOverOverdueReceptionConversationsAction;
use App\Enums\ConversationEventType;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Enums\HandoffReason;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Enums\ReceptionRoutingMode;
use App\Jobs\Reception\RunReceptionTurnJob;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\ConversationMessage;
use App\Models\ReceptionPlanVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

/**
 * 造一个绑定已发布方案的渠道（含策略覆盖），并全局注册可用接待模型。
 *
 * @return array{0: Channel, 1: ReceptionPlanVersion}
 */
function overdueTakeoverChannel(array $strategyOverrides = []): array
{
    $version = ReceptionPlanVersion::factory()->create();
    $snapshot = $version->snapshot_config;
    $snapshot['strategy_config'] = array_replace($snapshot['strategy_config'], $strategyOverrides);
    $version->update(['snapshot_config' => $snapshot]);

    $channel = Channel::factory()->create([
        'reception_plan_id' => $version->reception_plan_id,
    ]);

    makeAiModel();

    return [$channel, $version->refresh()];
}

/**
 * 在指定渠道下造一个开放会话。
 */
function overdueTakeoverConversation(Channel $channel, ReceptionPlanVersion $version, array $overrides = []): Conversation
{
    return Conversation::factory()->create(array_merge([
        'channel_id' => $channel->id,
        'reception_plan_version_id' => $version->id,
        'status' => ConversationStatus::Open,
    ], $overrides));
}

test('人工优先排队超时的会话被定时任务交回 AI 并补答积压消息', function () {
    Queue::fake();
    [$channel, $version] = overdueTakeoverChannel([
        'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
        'unassigned_ai_takeover_enabled' => true,
        'unassigned_ai_takeover_timeout_seconds' => 60,
    ]);
    $conversation = overdueTakeoverConversation($channel, $version, [
        'inbox_status' => ConversationInboxStatus::TeammatePending,
        'assigned_user_id' => null,
        'last_message_at' => now()->subMinutes(5),
    ]);
    $question = ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '请问怎么开发票？',
    ]);

    $result = app(TakeOverOverdueReceptionConversationsAction::class)->handle();

    $conversation->refresh();
    expect($result)->toBe(['taken_over' => 1])
        ->and($conversation->inbox_status)->toBe(ConversationInboxStatus::AiHandling)
        ->and($conversation->assigned_user_id)->toBeNull()
        ->and((string) $conversation->reception_plan_version_id)->toBe((string) $version->id);

    // 补答 turn 带上积压的访客问题，AI 直接回答而不是干等访客再开口。
    Queue::assertPushed(
        RunReceptionTurnJob::class,
        fn (RunReceptionTurnJob $job): bool => $job->conversationId === (string) $conversation->id
            && $job->aggregatedText === '请问怎么开发票？'
            && $job->messageIds === [(string) $question->id],
    );
});

test('未到排队超时或 AI 优先模式的排队会话不被定时接管', function () {
    Queue::fake();
    [$teammateFirstChannel, $teammateFirstVersion] = overdueTakeoverChannel([
        'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
        'unassigned_ai_takeover_enabled' => true,
        'unassigned_ai_takeover_timeout_seconds' => 600,
    ]);
    $notDue = overdueTakeoverConversation($teammateFirstChannel, $teammateFirstVersion, [
        'inbox_status' => ConversationInboxStatus::TeammatePending,
        'assigned_user_id' => null,
        'last_message_at' => now()->subMinutes(5),
    ]);

    // 排队超时转 AI 仅人工优先模式生效：AI 优先下转人工进队列表示 AI 已让位，不自动抢回。
    [$aiFirstChannel, $aiFirstVersion] = overdueTakeoverChannel([
        'reception_mode' => ReceptionRoutingMode::AiFirst->value,
        'unassigned_ai_takeover_enabled' => true,
        'unassigned_ai_takeover_timeout_seconds' => 60,
    ]);
    $aiFirstPending = overdueTakeoverConversation($aiFirstChannel, $aiFirstVersion, [
        'inbox_status' => ConversationInboxStatus::TeammatePending,
        'assigned_user_id' => null,
        'last_message_at' => now()->subHour(),
    ]);

    $result = app(TakeOverOverdueReceptionConversationsAction::class)->handle();

    expect($result)->toBe(['taken_over' => 0])
        ->and($notDue->refresh()->inbox_status)->toBe(ConversationInboxStatus::TeammatePending)
        ->and($aiFirstPending->refresh()->inbox_status)->toBe(ConversationInboxStatus::TeammatePending);
    Queue::assertNotPushed(RunReceptionTurnJob::class);
});

test('坐席无响应超时的会话被定时任务解除分配并交回 AI', function () {
    Queue::fake();
    [$channel, $version] = overdueTakeoverChannel([
        'teammate_no_response_ai_takeover_enabled' => true,
        'teammate_no_response_ai_takeover_timeout_seconds' => 60,
    ]);
    $teammate = User::factory()->create();
    $conversation = overdueTakeoverConversation($channel, $version, [
        'inbox_status' => ConversationInboxStatus::TeammateHandling,
        'assigned_user_id' => $teammate->id,
    ]);
    // 坐席无响应的判定基于「最后一条消息是访客发的且已超时」。
    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Text,
        'content' => '在吗？',
        'created_at' => now()->subMinutes(5),
    ]);

    $result = app(TakeOverOverdueReceptionConversationsAction::class)->handle();

    $conversation->refresh();
    expect($result)->toBe(['taken_over' => 1])
        ->and($conversation->inbox_status)->toBe(ConversationInboxStatus::AiHandling)
        ->and($conversation->assigned_user_id)->toBeNull();
    Queue::assertPushed(RunReceptionTurnJob::class);
});

test('AI 不可用冷却期内的排队会话不被定时接管', function () {
    Queue::fake();
    [$channel, $version] = overdueTakeoverChannel([
        'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
        'unassigned_ai_takeover_enabled' => true,
        'unassigned_ai_takeover_timeout_seconds' => 60,
    ]);
    $conversation = overdueTakeoverConversation($channel, $version, [
        'inbox_status' => ConversationInboxStatus::TeammatePending,
        'assigned_user_id' => null,
        'last_message_at' => now()->subMinutes(5),
    ]);
    // 刚因 AI 全模型失败转人工：冷却期内不应立刻切回 AI 造成反复横跳。
    ConversationEvent::query()->create([
        'conversation_id' => $conversation->id,
        'type' => ConversationEventType::HandoffRequested,
        'payload' => ['reason' => HandoffReason::AiUnavailable->value],
        'created_at' => now()->subMinute(),
    ]);

    $result = app(TakeOverOverdueReceptionConversationsAction::class)->handle();

    expect($result)->toBe(['taken_over' => 0])
        ->and($conversation->refresh()->inbox_status)->toBe(ConversationInboxStatus::TeammatePending);
});

test('定时接管命令可执行', function () {
    Queue::fake();
    [$channel, $version] = overdueTakeoverChannel([
        'reception_mode' => ReceptionRoutingMode::TeammateFirst->value,
        'unassigned_ai_takeover_enabled' => true,
        'unassigned_ai_takeover_timeout_seconds' => 60,
    ]);
    $conversation = overdueTakeoverConversation($channel, $version, [
        'inbox_status' => ConversationInboxStatus::TeammatePending,
        'assigned_user_id' => null,
        'last_message_at' => now()->subMinutes(5),
    ]);

    $this->artisan('reception:take-over-overdue-conversations')->assertSuccessful();

    expect($conversation->refresh()->inbox_status)->toBe(ConversationInboxStatus::AiHandling);
});
