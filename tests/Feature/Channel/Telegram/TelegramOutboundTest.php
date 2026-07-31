<?php

use App\Actions\Channel\ReconcileMessageOutboxesAction;
use App\Actions\Reception\AppendTelegramVisitorMessageAction;
use App\Data\Reception\ReceptionStateData;
use App\Enums\ChannelType;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageKind;
use App\Enums\MessageOutboxStatus;
use App\Enums\MessageRole;
use App\Jobs\Telegram\SendTelegramMessageJob;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Reception\ReceptionStateBuilder;
use App\Services\Telegram\TelegramBotApi;
use App\Services\Telegram\TelegramHtmlConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\WithInstance;

require_once __DIR__.'/TelegramTestSupport.php';

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
    Http::fake([
        '*/getUserProfilePhotos' => Http::response(['ok' => true, 'result' => ['photos' => []]]),
    ]);
});

/**
 * 建一个 Telegram 渠道并落一条访客消息，返回 [channel, conversation]。
 */
function seedTelegramConversation(): array
{
    $app = test()->instance;
    $version = createTelegramDeployablePlanVersion($app);
    $channel = Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
    ]);

    $result = app(AppendTelegramVisitorMessageAction::class)->handle(
        $channel->code,
        '55501',
        '访客',
        '你好',
        8001,
        55501,
    );

    return [$channel, $result['conversation']];
}

test('AI 出站消息会派发 Telegram 发送任务并先置 sending', function () {
    Queue::fake();
    [, $conversation] = seedTelegramConversation();

    $ai = ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Ai,
        'kind' => MessageKind::Text,
        'content' => '您好，请问有什么可以帮您？',
        'sender_name' => 'AI',
    ]);

    // 发出前先标 sending，避免落库即谎报 sent。
    expect($ai->fresh()->delivery_status)->toBe(MessageDeliveryStatus::Sending);
    Queue::assertPushed(SendTelegramMessageJob::class, 1);
});

test('Telegram 渠道可解析 AI 接待身份而不依赖访客界面设置', function () {
    [$channel, $conversation] = seedTelegramConversation();

    // Telegram settings 无 visitor_interface，应回退到 persona / 默认助手名而非报错。
    [$name, $avatar] = ReceptionStateBuilder::channelMessageIdentity($channel, $conversation);

    expect($name)->toBeString()
        ->and($name)->not->toBe('')
        ->and($avatar)->toBeNull();
});

test('Telegram 渠道可构建接待状态而不依赖访客界面设置', function () {
    [$channel, $conversation] = seedTelegramConversation();

    // build() 曾直接取 settings->visitor_interface，对 Telegram 渠道会 Undefined property 报错。
    $state = ReceptionStateBuilder::build($channel, $conversation, 'session-token');

    expect($state)->toBeInstanceOf(ReceptionStateData::class)
        ->and($state->conversation_id)->toBe((string) $conversation->id)
        ->and($state->assistant_name)->toBeString()
        ->and($state->assistant_name)->not->toBe('');
});

test('访客入站消息不派发出站任务', function () {
    Queue::fake();
    seedTelegramConversation();

    Queue::assertNotPushed(SendTelegramMessageJob::class);
});

test('Telegram 发送任务调用 Bot API 并标记已送达', function () {
    Http::fake([
        '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 9100]]),
    ]);
    [$channel, $conversation] = seedTelegramConversation();

    $ai = ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Ai,
        'kind' => MessageKind::Text,
        'content' => '订单已为您查询到。',
        'sender_name' => 'AI',
    ]);

    (new SendTelegramMessageJob((string) $ai->id))->handle(app(TelegramBotApi::class), app(TelegramHtmlConverter::class));

    expect($ai->fresh()->delivery_status)->toBe(MessageDeliveryStatus::Sent)
        ->and($ai->fresh()->payload['telegram']['message_id'] ?? null)->toBe(9100);

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/sendMessage')
            && $request['chat_id'] === 55501
            && $request['text'] === '订单已为您查询到。'
            && $request['parse_mode'] === 'HTML';
    });
});

test('Telegram 出站把 Markdown 转成 HTML 子集发送', function () {
    Http::fake([
        '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 9200]]),
    ]);
    [, $conversation] = seedTelegramConversation();

    $ai = ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Ai,
        'kind' => MessageKind::Text,
        'content' => '**重点**：请看[文档](https://example.com)，特殊字符 < & 已转义',
        'sender_name' => 'AI',
    ]);

    (new SendTelegramMessageJob((string) $ai->id))->handle(app(TelegramBotApi::class), app(TelegramHtmlConverter::class));

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/sendMessage')
            && $request['parse_mode'] === 'HTML'
            && $request['text'] === '<b>重点</b>：请看<a href="https://example.com">文档</a>，特殊字符 &lt; &amp; 已转义';
    });
});

test('Telegram 发送失败标记投递失败', function () {
    Http::fake([
        '*/sendMessage' => Http::response(['ok' => false, 'error_code' => 403, 'description' => 'Forbidden: bot was blocked by the user'], 403),
    ]);
    [, $conversation] = seedTelegramConversation();

    $ai = ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Ai,
        'kind' => MessageKind::Text,
        'content' => '您好',
        'sender_name' => 'AI',
    ]);

    (new SendTelegramMessageJob((string) $ai->id))->handle(app(TelegramBotApi::class), app(TelegramHtmlConverter::class));

    expect($ai->fresh()->delivery_status)->toBe(MessageDeliveryStatus::Failed);
});

test('对账任务重投卡在 sending 的出站消息', function () {
    Queue::fake();
    [, $conversation] = seedTelegramConversation();

    $stuck = ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Ai,
        'kind' => MessageKind::Text,
        'content' => '卡住的消息',
        'sender_name' => 'AI',
    ]);
    $stuck->outbox->forceFill([
        'status' => MessageOutboxStatus::Sending,
        'locked_at' => now()->subSeconds(ReconcileMessageOutboxesAction::STUCK_AFTER_SECONDS + 60),
        'lock_token' => 'expired-lease',
    ])->saveQuietly();
    expect($stuck->fresh()->delivery_status)->toBe(MessageDeliveryStatus::Sending);

    $count = ReconcileMessageOutboxesAction::run(ChannelType::Telegram);

    expect($count)->toBe(1);
    Queue::assertPushed(SendTelegramMessageJob::class, fn (SendTelegramMessageJob $job) => $job->messageId === (string) $stuck->id);
});

test('对账任务不重投仍在重试窗口内的消息', function () {
    Queue::fake();
    [, $conversation] = seedTelegramConversation();

    ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Ai,
        'kind' => MessageKind::Text,
        'content' => '刚发出的消息',
        'sender_name' => 'AI',
    ]);

    $count = ReconcileMessageOutboxesAction::run(ChannelType::Telegram);

    expect($count)->toBe(0);
});
