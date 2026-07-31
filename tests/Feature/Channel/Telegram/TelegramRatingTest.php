<?php

use App\Actions\Reception\CloseConversationAction;
use App\Actions\Reception\ResolveTelegramReceptionContextAction;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Jobs\Telegram\SendTelegramRatingPromptJob;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Models\Conversation;
use App\Services\Telegram\TelegramBotApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
});

/**
 * 造一个带已知 webhook_secret 的 Telegram 渠道及其下的会话。
 */
function telegramRatingConversation(string $status = 'closed'): array
{
    $app = test()->instance;
    $channel = Channel::factory()->telegram()->create([]);
    $contact = Contact::factory()->visitor()->create([]);
    $conversation = Conversation::factory()->forContact($contact)->create([
        'channel_id' => $channel->id,
        'status' => $status === 'closed' ? ConversationStatus::Closed : ConversationStatus::Open,
        // 评价资格依赖关单时的接待状态（排队中不邀评），固定为 AI 接待避免工厂随机值导致偶发失败。
        'inbox_status' => ConversationInboxStatus::AiHandling,
        'closed_at' => $status === 'closed' ? now() : null,
    ]);

    return [$channel, $conversation];
}

test('关闭 Telegram 会话派发评价提示任务', function () {
    Bus::fake();
    [, $conversation] = telegramRatingConversation('open');

    CloseConversationAction::run($conversation);

    Bus::assertDispatched(
        SendTelegramRatingPromptJob::class,
        fn (SendTelegramRatingPromptJob $job): bool => $job->conversationId === (string) $conversation->id,
    );
});

test('Telegram 评价按钮回调落库评分并应答', function () {
    Http::fake([
        '*/answerCallbackQuery' => Http::response(['ok' => true, 'result' => true]),
        '*/editMessageText' => Http::response(['ok' => true, 'result' => []]),
    ]);
    [$channel, $conversation] = telegramRatingConversation('closed');

    $this->postJson("/webhook/telegram/{$channel->code}", [
        'update_id' => 91001,
        'callback_query' => [
            'id' => 'cbq-1',
            'from' => ['id' => 12345],
            'data' => 'csat:positive:'.$conversation->id,
            'message' => ['message_id' => 99, 'chat' => ['id' => 12345]],
        ],
    ], ['X-Telegram-Bot-Api-Secret-Token' => $channel->settings->webhook_secret])
        ->assertOk();

    $this->assertDatabaseHas('conversation_ratings', [
        'conversation_id' => $conversation->id,
        'score' => 'positive',
        'channel_type' => 'telegram',
        'handled_by' => 'ai',
    ]);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'answerCallbackQuery'));
});

test('Telegram 评价提示按访客界面语言发送', function () {
    Http::fake(['*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);

    $app = test()->instance;
    $channel = Channel::factory()->telegram()->create([]);
    $contact = Contact::factory()->visitor()->create([]);
    ContactIdentity::factory()
        ->channelAccount('777', ResolveTelegramReceptionContextAction::identityNamespace($channel->code))
        ->create(['contact_id' => $contact->id]);
    $conversation = Conversation::factory()->forContact($contact)->create([
        'channel_id' => $channel->id,
        'status' => ConversationStatus::Closed,
        'closed_at' => now(),
        'visitor_locale' => 'en',
    ]);

    (new SendTelegramRatingPromptJob((string) $conversation->id))->handle(app(TelegramBotApi::class));

    Http::assertSent(
        fn ($request): bool => str_contains($request->url(), 'sendMessage')
            && ($request['text'] ?? '') === 'How was your experience?',
    );
});

test('Telegram 错误 secret 的回调被拒', function () {
    [$channel, $conversation] = telegramRatingConversation('closed');

    $this->postJson("/webhook/telegram/{$channel->code}", [
        'update_id' => 91002,
        'callback_query' => [
            'id' => 'cbq-2',
            'from' => ['id' => 12345],
            'data' => 'csat:positive:'.$conversation->id,
            'message' => ['message_id' => 99, 'chat' => ['id' => 12345]],
        ],
    ], ['X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret'])
        ->assertForbidden();

    $this->assertDatabaseMissing('conversation_ratings', ['conversation_id' => $conversation->id]);
});
