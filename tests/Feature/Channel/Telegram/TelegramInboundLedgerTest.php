<?php

use App\Actions\Channel\Telegram\ReconcileTelegramInboundUpdatesAction;
use App\Enums\TelegramInboundUpdateStatus;
use App\Jobs\Telegram\ProcessTelegramInboundUpdateJob;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\TelegramInboundUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\WithInstance;

require_once __DIR__.'/TelegramTestSupport.php';

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
});

/** 创建用于入站台账测试的 Telegram 渠道。 */
function makeTelegramInboundLedgerChannel(): Channel
{
    $version = createTelegramDeployablePlanVersion(test()->instance);

    return Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
    ]);
}

test('Telegram webhook 按 update_id 去重并只派发一次', function () {
    Queue::fake();
    $channel = makeTelegramInboundLedgerChannel();
    $message = [
        'message_id' => 95001,
        'from' => ['id' => 78001, 'first_name' => '访客'],
        'chat' => ['id' => 78001, 'type' => 'private'],
        'text' => '需要帮助',
    ];

    postTelegramUpdate($channel, $message)->assertOk();
    postTelegramUpdate($channel, $message)->assertOk();

    expect(TelegramInboundUpdate::query()->count())->toBe(1);
    Queue::assertPushed(ProcessTelegramInboundUpdateJob::class, 1);
});

test('Telegram 入站任务完成消息落库并更新台账状态', function () {
    Http::fake([
        '*/getUserProfilePhotos' => Http::response(['ok' => true, 'result' => ['photos' => []]]),
        '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
    ]);
    $channel = makeTelegramInboundLedgerChannel();

    postTelegramUpdate($channel, [
        'message_id' => 95003,
        'from' => ['id' => 78003, 'first_name' => '访客'],
        'chat' => ['id' => 78003, 'type' => 'private'],
        'text' => '查询订单',
    ])->assertOk();

    $inbound = TelegramInboundUpdate::query()->firstOrFail();
    expect($inbound->status)->toBe(TelegramInboundUpdateStatus::Processed)
        ->and($inbound->attempts)->toBe(1)
        ->and(ConversationMessage::query()->where('client_msg_id', 'tg_95003')->exists())->toBeTrue();
});

test('Telegram 启动命令回复静态引导且不创建接待资源', function () {
    Http::fake([
        '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
    ]);
    $channel = makeTelegramInboundLedgerChannel();

    foreach (['/start', '/start ref_campaign_42'] as $index => $text) {
        $messageId = 95100 + $index;
        postTelegramUpdate($channel, [
            'message_id' => $messageId,
            'from' => ['id' => 78100 + $index, 'first_name' => '新访客', 'language_code' => 'zh-hans'],
            'chat' => ['id' => 78100 + $index, 'type' => 'private'],
            'text' => $text,
        ])->assertOk();
    }

    expect(Contact::query()->count())->toBe(0)
        ->and(Conversation::query()->count())->toBe(0)
        ->and(ConversationMessage::query()->count())->toBe(0)
        ->and(Http::recorded(fn ($request): bool => str_contains($request->url(), '/sendMessage')))->toHaveCount(2);
});

test('Telegram 入站对账预占记录后重新派发', function () {
    Queue::fake();
    $channel = makeTelegramInboundLedgerChannel();
    $inbound = TelegramInboundUpdate::query()->create([
        'channel_id' => $channel->id,
        'provider_update_id' => '95002',
        'update_type' => 'message',
        'payload' => ['update_id' => 95002, 'message' => []],
        'status' => TelegramInboundUpdateStatus::Pending,
        'available_at' => now()->subMinute(),
    ]);

    expect(ReconcileTelegramInboundUpdatesAction::run())->toBe(1)
        ->and(ReconcileTelegramInboundUpdatesAction::run())->toBe(0)
        ->and($inbound->fresh()->available_at?->isFuture())->toBeTrue();
    Queue::assertPushed(ProcessTelegramInboundUpdateJob::class, 1);
    $job = Queue::pushed(ProcessTelegramInboundUpdateJob::class)->first();
    expect($job->reservationToken)->not->toBeNull()
        ->and($inbound->fresh()->claimForProcessing($job->reservationToken))->not->toBeNull();
});
