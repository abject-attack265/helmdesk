<?php

use App\Actions\Reception\CloseIdleReceptionConversationsAction;
use App\Enums\ChannelType;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Jobs\Telegram\SendTelegramRatingPromptJob;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ReceptionPlanVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('超过空闲时长的会话不区分接待状态一律关闭', function () {
    // 默认方案：开启空闲自动结束、空闲 10 分钟。
    $version = ReceptionPlanVersion::factory()->create();

    $idleByStatus = collect([
        ConversationInboxStatus::AiHandling,
        ConversationInboxStatus::TeammatePending,
        ConversationInboxStatus::TeammateHandling,
    ])->mapWithKeys(fn (ConversationInboxStatus $status) => [
        $status->value => Conversation::factory()->create([
            'reception_plan_version_id' => $version->id,
            'status' => ConversationStatus::Open,
            'inbox_status' => $status,
            'last_message_at' => now()->subMinutes(15),
        ]),
    ]);

    $result = app(CloseIdleReceptionConversationsAction::class)->handle();

    expect($result)->toBe(['closed' => 3]);
    foreach ($idleByStatus as $conversation) {
        expect($conversation->refresh()->status)->toBe(ConversationStatus::Closed)
            ->and($conversation->closed_at)->not->toBeNull();
    }
});

test('未超空闲时长或方案关闭空闲自动结束的会话不关闭', function () {
    $enabledVersion = ReceptionPlanVersion::factory()->create();

    $disabledVersion = ReceptionPlanVersion::factory()->create();
    $disabledSnapshot = $disabledVersion->snapshot_config;
    $disabledSnapshot['strategy_config']['auto_close_enabled'] = false;
    $disabledVersion->update(['snapshot_config' => $disabledSnapshot]);

    $active = Conversation::factory()->create([
        'reception_plan_version_id' => $enabledVersion->id,
        'status' => ConversationStatus::Open,
        'inbox_status' => ConversationInboxStatus::TeammateHandling,
        'last_message_at' => now()->subMinutes(5),
    ]);
    $disabled = Conversation::factory()->create([
        'reception_plan_version_id' => $disabledVersion->id,
        'status' => ConversationStatus::Open,
        'inbox_status' => ConversationInboxStatus::TeammateHandling,
        'last_message_at' => now()->subDay(),
    ]);

    $result = app(CloseIdleReceptionConversationsAction::class)->handle();

    expect($result)->toBe(['closed' => 0])
        ->and($active->refresh()->status)->toBe(ConversationStatus::Open)
        ->and($disabled->refresh()->status)->toBe(ConversationStatus::Open);
});

test('刚重新打开的会话按重开时间重新计算空闲窗口，不被立即关闭', function () {
    // 默认方案空闲 10 分钟：最后消息早已超时，但刚重新打开应重获完整空闲窗口。
    $version = ReceptionPlanVersion::factory()->create();

    $reopened = Conversation::factory()->create([
        'reception_plan_version_id' => $version->id,
        'status' => ConversationStatus::Open,
        'inbox_status' => ConversationInboxStatus::TeammateHandling,
        'last_message_at' => now()->subHour(),
        'reopened_at' => now()->subMinutes(1),
    ]);

    $result = app(CloseIdleReceptionConversationsAction::class)->handle();

    expect($result)->toBe(['closed' => 0])
        ->and($reopened->refresh()->status)->toBe(ConversationStatus::Open);
});

test('重新打开后仍空闲超过阈值的会话会被关闭', function () {
    $version = ReceptionPlanVersion::factory()->create();

    $reopened = Conversation::factory()->create([
        'reception_plan_version_id' => $version->id,
        'status' => ConversationStatus::Open,
        'inbox_status' => ConversationInboxStatus::TeammateHandling,
        'last_message_at' => now()->subHour(),
        'reopened_at' => now()->subMinutes(15),
    ]);

    $result = app(CloseIdleReceptionConversationsAction::class)->handle();

    expect($result)->toBe(['closed' => 1])
        ->and($reopened->refresh()->status)->toBe(ConversationStatus::Closed);
});

test('Telegram 会话关单后推送评价按钮，排队中无人接待过的除外', function () {
    Queue::fake();
    $version = ReceptionPlanVersion::factory()->create();
    $channel = Channel::factory()->create(['type' => ChannelType::Telegram]);

    $handled = Conversation::factory()->create([
        'reception_plan_version_id' => $version->id,
        'channel_id' => $channel->id,
        'status' => ConversationStatus::Open,
        'inbox_status' => ConversationInboxStatus::AiHandling,
        'last_message_at' => now()->subHour(),
    ]);
    $pending = Conversation::factory()->create([
        'reception_plan_version_id' => $version->id,
        'channel_id' => $channel->id,
        'status' => ConversationStatus::Open,
        'inbox_status' => ConversationInboxStatus::TeammatePending,
        'last_message_at' => now()->subHour(),
    ]);

    $result = app(CloseIdleReceptionConversationsAction::class)->handle();

    expect($result)->toBe(['closed' => 2])
        ->and($handled->refresh()->status)->toBe(ConversationStatus::Closed)
        ->and($pending->refresh()->status)->toBe(ConversationStatus::Closed);

    Queue::assertPushed(
        SendTelegramRatingPromptJob::class,
        fn (SendTelegramRatingPromptJob $job): bool => $job->conversationId === (string) $handled->id,
    );
    Queue::assertNotPushed(
        SendTelegramRatingPromptJob::class,
        fn (SendTelegramRatingPromptJob $job): bool => $job->conversationId === (string) $pending->id,
    );
});

test('定时命令可执行并完成空闲会话关闭', function () {
    $version = ReceptionPlanVersion::factory()->create();
    $idle = Conversation::factory()->create([
        'reception_plan_version_id' => $version->id,
        'status' => ConversationStatus::Open,
        'inbox_status' => ConversationInboxStatus::TeammateHandling,
        'last_message_at' => now()->subMinutes(20),
    ]);

    $this->artisan('reception:close-idle-conversations')->assertSuccessful();

    expect($idle->refresh()->status)->toBe(ConversationStatus::Closed);
});
