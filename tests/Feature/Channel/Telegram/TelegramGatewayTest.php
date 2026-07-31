<?php

use App\Actions\Contact\ResolveContactIdentityAction;
use App\Enums\ContactSource;
use App\Enums\IdentityType;
use App\Enums\TelegramWebhookMode;
use App\Models\Channel;
use App\Models\ContactIdentity;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\WithInstance;

require_once __DIR__.'/TelegramTestSupport.php';

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();

    // 默认模拟 Telegram 用户没有头像，避免首次联系触发真实 Bot API 请求。
    Http::fake([
        '*/getUserProfilePhotos' => Http::response(['ok' => true, 'result' => ['photos' => []]]),
        '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
    ]);
});

/**
 * 创建一个带已部署接待方案的 Telegram 渠道。
 */
function makeGatewayTelegramChannel(array $attributes = []): Channel
{
    $app = test()->instance;
    $version = createTelegramDeployablePlanVersion($app);

    return Channel::factory()->telegram()->create(array_merge([
        'reception_plan_id' => $version->reception_plan_id,
    ], $attributes));
}

/**
 * 把渠道切到网关托管模式（网关身份头只在该模式下被解析）。
 */
function enableGatewayMode(Channel $channel): void
{
    $channel->update([
        'settings' => $channel->settings->withWebhookMode(TelegramWebhookMode::Gateway),
    ]);
}

/**
 * 模拟业务网关转发：带渠道 secret 与可选的网关身份头 POST 一条 message 更新。
 *
 * @param  array<string, mixed>  $message
 * @param  array<string, string>  $gatewayHeaders
 */
function postForwardedTelegramUpdate(Channel $channel, array $message, array $gatewayHeaders = []): TestResponse
{
    return test()->postJson(
        "/webhook/telegram/{$channel->code}",
        [
            'update_id' => $message['message_id'],
            'message' => $message,
        ],
        array_merge(
            ['X-Telegram-Bot-Api-Secret-Token' => $channel->settings->webhook_secret],
            $gatewayHeaders,
        ),
    );
}

/**
 * 构造一条标准的 Telegram 私聊文本 message。
 *
 * @return array<string, mixed>
 */
function telegramTextMessage(int $messageId, string $text, int $userId = 99001): array
{
    return [
        'message_id' => $messageId,
        'from' => ['id' => $userId, 'first_name' => '小明'],
        'chat' => ['id' => $userId, 'type' => 'private'],
        'date' => 1751000000,
        'text' => $text,
    ];
}

test('网关托管模式下注册 webhook 被拒绝且不发起 Telegram 请求', function () {
    $channel = makeGatewayTelegramChannel();
    $channel->update([
        'settings' => $channel->settings->withWebhookMode(TelegramWebhookMode::Gateway),
    ]);
    $registeredAtBefore = $channel->fresh()->settings->webhook_registered_at;

    $this->actingAs($this->user)
        ->from(route('app.manage.channels.telegram.show', [
            'channel' => $channel->id,
        ]))
        ->post(route('app.manage.channels.telegram.webhook.register', [
            'channel' => $channel->id,
        ]))
        // BusinessException → 422，由前端统一拦截为 error toast。
        ->assertStatus(422);

    expect($channel->fresh()->settings->webhook_registered_at)->toBe($registeredAtBefore);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'setWebhook'));
});

test('直连模式注册时先删除旧 webhook 再注册', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true, 'result' => true]),
    ]);

    $channel = makeGatewayTelegramChannel();

    $this->actingAs($this->user)
        ->post(route('app.manage.channels.telegram.webhook.register', [
            'channel' => $channel->id,
        ]))
        ->assertRedirect();

    Http::assertSentInOrder([
        fn ($request) => str_contains($request->url(), 'deleteWebhook'),
        fn ($request) => str_contains($request->url(), 'setWebhook'),
    ]);
    expect($channel->fresh()->settings->webhook_registered_at)->not->toBeNull();
});

test('基本信息保存切换 webhook 归属模式，切到网关托管时清空本地注册时间', function () {
    Http::fake([
        '*/deleteWebhook' => Http::response(['ok' => true, 'result' => true]),
        '*/setWebhook' => Http::response(['ok' => true, 'result' => true]),
    ]);
    $channel = makeGatewayTelegramChannel();
    $channel->update([
        'settings' => $channel->settings->withWebhookRegisteredAt(now()->toIso8601String()),
    ]);
    $originalSecret = $channel->fresh()->settings->webhook_secret;

    $basicUpdatePayload = fn (string $mode): array => [
        'name' => $channel->name,
        'reception_plan_id' => $channel->reception_plan_id,
        'default_visitor_locale' => 'zh-CN',
        'webhook_mode' => $mode,
        'bot_token' => '',
    ];

    $this->actingAs($this->user)
        ->put(route('app.manage.channels.telegram.basic.update', [
            'channel' => $channel->id,
        ]), $basicUpdatePayload('gateway'))
        ->assertRedirect();

    $settings = $channel->fresh()->settings;
    expect($settings->webhook_mode)->toBe(TelegramWebhookMode::Gateway)
        ->and($settings->webhook_registered_at)->toBeNull()
        ->and($settings->webhook_secret)->not->toBe($originalSecret);
    $gatewaySecret = $settings->webhook_secret;

    $this->actingAs($this->user)
        ->put(route('app.manage.channels.telegram.basic.update', [
            'channel' => $channel->id,
        ]), $basicUpdatePayload('direct'))
        ->assertRedirect();

    $settings = $channel->fresh()->settings;
    expect($settings->webhook_mode)->toBe(TelegramWebhookMode::Direct)
        ->and($settings->webhook_secret)->not->toBe($gatewaySecret)
        ->and($settings->webhook_registered_at)->not->toBeNull();
});

test('网关注入身份头时给联系人挂载业务身份与邮箱', function () {
    $channel = makeGatewayTelegramChannel();
    enableGatewayMode($channel);

    postForwardedTelegramUpdate($channel, telegramTextMessage(6001, '我的订单没到账'), [
        'X-Gateway-External-Id' => '4567',
        'X-Gateway-External-Email' => 'user@example.com',
    ])->assertOk();

    $conversation = Conversation::query()->firstOrFail();
    $identities = ContactIdentity::query()
        ->where('contact_id', $conversation->contact_id)
        ->whereNull('deleted_at')
        ->get();

    $external = $identities->first(fn (ContactIdentity $i) => $i->type === IdentityType::ExternalId);
    expect($external)->not->toBeNull()
        ->and($external->value)->toBe('4567');

    $email = $identities->first(fn (ContactIdentity $i) => $i->type === IdentityType::Email);
    expect($email)->not->toBeNull()
        ->and($email->value)->toBe('user@example.com');
});

test('业务身份已属于其他联系人时自动合并，会话归入既有联系人', function () {
    $channel = makeGatewayTelegramChannel();
    enableGatewayMode($channel);

    // 同一业务用户已通过网页渠道建立联系人并绑定外部 ID。
    $existingContact = app(ResolveContactIdentityAction::class)->handle(
        ['type' => IdentityType::ExternalId, 'value' => '4567'],
        ContactSource::Web,
        name: '网页老联系人',
    );

    postForwardedTelegramUpdate($channel, telegramTextMessage(6002, '换到 Telegram 咨询'), [
        'X-Gateway-External-Id' => '4567',
    ])->assertOk();

    // Telegram 会话归入既有联系人，telegram 渠道账号身份也随合并转移。
    $conversation = Conversation::query()->firstOrFail();
    expect((string) $conversation->fresh()->contact_id)->toBe((string) $existingContact->id);

    $telegramIdentity = ContactIdentity::query()
        ->where('type', IdentityType::ChannelAccount)
        ->where('namespace', 'telegram:'.$channel->code)
        ->whereNull('deleted_at')
        ->firstOrFail();
    expect((string) $telegramIdentity->contact_id)->toBe((string) $existingContact->id);
});

test('直连模式渠道忽略网关身份头', function () {
    $channel = makeGatewayTelegramChannel();

    postForwardedTelegramUpdate($channel, telegramTextMessage(6006, '你好'), [
        'X-Gateway-External-Id' => '4567',
    ])->assertOk();

    expect(ContactIdentity::query()
        ->where('type', IdentityType::ExternalId)
        ->exists())->toBeFalse();
});

test('编辑消息原地更新正文并打已编辑标记，最新消息时同步会话预览', function () {
    $channel = makeGatewayTelegramChannel();

    postForwardedTelegramUpdate($channel, telegramTextMessage(6004, '原始文本'))->assertOk();

    $this->postJson("/webhook/telegram/{$channel->code}", [
        'update_id' => 6004001,
        'edited_message' => telegramTextMessage(6004, '编辑后的文本'),
    ], [
        'X-Telegram-Bot-Api-Secret-Token' => $channel->settings->webhook_secret,
    ])->assertOk();

    $message = ConversationMessage::query()
        ->where('client_msg_id', 'tg_6004')
        ->firstOrFail();

    expect((string) $message->content)->toBe('编辑后的文本')
        ->and($message->payload['edited_at'] ?? null)->not->toBeNull();

    $conversation = Conversation::query()->firstOrFail();
    expect((string) $conversation->last_message_preview)->toContain('编辑后的文本');
});

test('编辑接入前的未知消息时静默确认不报错', function () {
    $channel = makeGatewayTelegramChannel();

    $this->postJson("/webhook/telegram/{$channel->code}", [
        'update_id' => 999999001,
        'edited_message' => telegramTextMessage(999999, '编辑一条不存在的消息'),
    ], [
        'X-Telegram-Bot-Api-Secret-Token' => $channel->settings->webhook_secret,
    ])->assertOk();

    expect(ConversationMessage::query()->count())->toBe(0);
});

test('编辑消息带错误 secret 时拒绝', function () {
    $channel = makeGatewayTelegramChannel();

    $this->postJson("/webhook/telegram/{$channel->code}", [
        'update_id' => 6005001,
        'edited_message' => telegramTextMessage(6005, '恶意编辑'),
    ], [
        'X-Telegram-Bot-Api-Secret-Token' => 'wrong-secret',
    ])->assertForbidden();
});
