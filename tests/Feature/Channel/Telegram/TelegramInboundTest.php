<?php

use App\Actions\Reception\AppendTelegramVisitorMessageAction;
use App\Actions\Reception\UpdateTelegramVisitorMessageAction;
use App\Enums\ContactSource;
use App\Enums\ConversationEntryMode;
use App\Enums\IdentityType;
use App\Enums\MessageRole;
use App\Models\Attachment;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Models\ConversationMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\WithInstance;

require_once __DIR__.'/TelegramTestSupport.php';

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
});

/**
 * 默认模拟 Telegram 用户没有头像，避免非头像测试发起真实 Bot API 请求。
 */
function fakeTelegramProfilePhotosUnavailable(): void
{
    Http::fake([
        '*/getUserProfilePhotos' => Http::response(['ok' => true, 'result' => ['photos' => []]]),
        '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
    ]);
}

/**
 * 创建一个带已知 webhook_secret、已部署接待方案的 Telegram 渠道。
 */
function makeInboundTelegramChannel(): Channel
{
    $app = test()->instance;
    $version = createTelegramDeployablePlanVersion($app);

    return Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
    ]);
}

test('Telegram 入站消息创建会话与访客消息', function () {
    fakeTelegramProfilePhotosUnavailable();

    $channel = makeInboundTelegramChannel();

    $result = app(AppendTelegramVisitorMessageAction::class)->handle(
        $channel->code,
        '99001',
        '小明',
        '你好，我要咨询订单',
        5001,
        99001,
        'xiaoming',
    );

    $conversation = $result['conversation'];
    $message = $result['message'];

    expect($message)->not->toBeNull();
    expect($conversation->channel_id)->toBe($channel->id)
        ->and($conversation->entry_mode)->toBe(ConversationEntryMode::Telegram);

    expect($message->role)->toBe(MessageRole::Visitor)
        ->and($message->content)->toBe('你好，我要咨询订单')
        ->and($message->payload['telegram']['message_id'] ?? null)->toBe(5001)
        ->and($message->client_msg_id)->toBe('tg_5001');

    // 访客身份以 ChannelAccount（渠道账号）+ telegram:{code} namespace 落库。
    $identity = ContactIdentity::query()

        ->where('type', IdentityType::ChannelAccount)
        ->where('namespace', 'telegram:'.$channel->code)
        ->where('value', '99001')
        ->first();
    expect($identity)->not->toBeNull()
        ->and($identity->contact->source)->toBe(ContactSource::Telegram);
});

test('Telegram 入站消息会同步用户头像到联系人', function () {
    fakeAttachmentStorage();
    Http::fake([
        '*/getUserProfilePhotos' => Http::response([
            'ok' => true,
            'result' => [
                'photos' => [[
                    ['file_id' => 'small-avatar'],
                    ['file_id' => 'large-avatar'],
                ]],
            ],
        ]),
        '*/getFile' => Http::response(['ok' => true, 'result' => ['file_path' => 'photos/avatar.jpg']]),
        '*/file/bot*' => Http::response('AVATAR-BYTES'),
        '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
    ]);

    $channel = makeInboundTelegramChannel();

    app(AppendTelegramVisitorMessageAction::class)->handle(
        $channel->code,
        '99021',
        '头像用户',
        '你好',
        5101,
        99021,
        'avatar_user',
    );

    $identity = ContactIdentity::query()

        ->where('namespace', 'telegram:'.$channel->code)
        ->where('value', '99021')
        ->firstOrFail();
    $contact = $identity->contact->fresh();
    $attachment = Attachment::query()
        ->where('attachable_type', $contact->getMorphClass())
        ->where('attachable_id', $contact->getKey())
        ->firstOrFail();

    expect($contact->avatar_url)->toBe(route('attachments.content', ['attachment' => $attachment->id]))
        ->and($contact->avatar_synced_at)->not->toBeNull()
        ->and($attachment->purpose->value)->toBe('avatar')
        ->and($attachment->filesystem()->get($attachment->object_key))->toBe('AVATAR-BYTES');
});

test('Telegram 无头像访客只探测一次头像，后续消息不再请求 Bot API', function () {
    Http::fake([
        '*/getUserProfilePhotos' => Http::response(['ok' => true, 'result' => ['photos' => []]]),
        '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
    ]);

    $channel = makeInboundTelegramChannel();

    foreach ([5201, 5202] as $messageId) {
        app(AppendTelegramVisitorMessageAction::class)->handle(
            $channel->code,
            '99022',
            '无头像用户',
            '你好',
            $messageId,
            99022,
            'no_avatar_user',
        );
    }

    // 第一条消息探测一次后即打标 avatar_synced_at，第二条消息不应再请求 getUserProfilePhotos。
    $probeCount = collect(Http::recorded())
        ->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/getUserProfilePhotos'))
        ->count();

    $contact = ContactIdentity::query()

        ->where('namespace', 'telegram:'.$channel->code)
        ->where('value', '99022')
        ->firstOrFail()
        ->contact->fresh();

    expect($probeCount)->toBe(1)
        ->and($contact->avatar_synced_at)->not->toBeNull()
        ->and($contact->avatar_url)->toBe(Contact::DEFAULT_AVATAR_URL);
});

test('Telegram 入站对同一 message_id 幂等', function () {
    fakeTelegramProfilePhotosUnavailable();

    $channel = makeInboundTelegramChannel();

    $args = [$channel->code, '99002', '阿强', '重复消息', 6001, 99002];
    $first = app(AppendTelegramVisitorMessageAction::class)->handle(...$args);
    $second = app(AppendTelegramVisitorMessageAction::class)->handle(...$args);

    expect(ConversationMessage::query()->where('client_msg_id', 'tg_6001')->count())->toBe(1)
        ->and($second['message']?->id)->toBe($first['message']?->id);
});

test('Telegram 编辑消息重复处理时返回同一条消息', function () {
    fakeTelegramProfilePhotosUnavailable();
    $channel = makeInboundTelegramChannel();
    app(AppendTelegramVisitorMessageAction::class)->handle(
        $channel->code,
        '99004',
        '编辑用户',
        '原始内容',
        6002,
        99004,
    );

    $first = app(UpdateTelegramVisitorMessageAction::class)->handle($channel, 6002, '编辑内容');
    $retry = app(UpdateTelegramVisitorMessageAction::class)->handle($channel, 6002, '编辑内容');

    expect($retry['message']?->id)->toBe($first['message']?->id)
        ->and($retry['conversation']?->id)->toBe($first['conversation']?->id);
});

test('Telegram 入站 secret 不符时拒绝', function () {
    $channel = makeInboundTelegramChannel();

    postTelegramUpdate($channel, [
        'message_id' => 7001,
        'from' => ['id' => 99003, 'first_name' => '某人'],
        'chat' => ['id' => 99003, 'type' => 'private'],
        'text' => '你好',
    ], 'wrong-secret')->assertForbidden();
});
