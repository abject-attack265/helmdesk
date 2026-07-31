<?php

use App\Actions\Reception\ResolveTelegramReceptionContextAction;
use App\Enums\AttachmentPurpose;
use App\Enums\AttachmentStatus;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Jobs\Telegram\ProcessTelegramInboundUpdateJob;
use App\Jobs\Telegram\SendTelegramMessageJob;
use App\Models\Attachment;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\StorageProfile;
use App\Services\Telegram\TelegramBotApi;
use App\Services\Telegram\TelegramHtmlConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\WithInstance;

require_once __DIR__.'/TelegramTestSupport.php';

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
    fakeAttachmentStorage();
    // 入站经 webhook 落库后可能唤起接待 turn / 触发出站任务，统一拦截队列避免真实执行。
    Queue::fake()->except([ProcessTelegramInboundUpdateJob::class]);
});

/**
 * 建一个已部署接待方案的 Telegram 渠道（媒体测试用）。
 */
function makeMediaTelegramChannel(): Channel
{
    $app = test()->instance;
    $version = createTelegramDeployablePlanVersion($app);

    return Channel::factory()->telegram()->create([
        'reception_plan_id' => $version->reception_plan_id,
    ]);
}

/**
 * 返回可被真实内容检测识别的 PNG 字节。
 */
function telegramInboundPngBytes(): string
{
    return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
}

test('Telegram 入站图片下载并创建 Image 消息与附件', function () {
    Storage::fake('local');
    $png = telegramInboundPngBytes();
    Http::fake([
        '*/getFile' => Http::response(['ok' => true, 'result' => ['file_id' => 'F1', 'file_path' => 'photos/file_1.jpg']]),
        '*/file/bot*' => Http::response($png),
        // 清空会话创建时产生的欢迎语请求。
        '*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
    ]);

    $channel = makeMediaTelegramChannel();

    postTelegramUpdate($channel, [
        'message_id' => 9001,
        'from' => ['id' => 77001, 'first_name' => '访客'],
        'chat' => ['id' => 77001, 'type' => 'private'],
        'photo' => [['file_id' => 'F1']],
    ])->assertOk();

    $conversation = Conversation::query()->where('channel_id', $channel->id)->firstOrFail();
    $imageMessage = ConversationMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('kind', MessageKind::Image)
        ->first();

    expect($imageMessage)->not->toBeNull()
        ->and($imageMessage->client_msg_id)->toBe('tg_9001')
        ->and($imageMessage->payload['attachments'][0]['id'] ?? null)->not->toBeNull();

    $attachment = Attachment::query()
        ->where('attachable_type', $imageMessage->getMorphClass())
        ->where('attachable_id', $imageMessage->getKey())
        ->first();

    expect($attachment)->not->toBeNull()
        ->and($attachment->purpose)->toBe(AttachmentPurpose::ConversationImage)
        ->and($attachment->byte_size)->toBe(strlen($png))
        ->and($attachment->filesystem()->get($attachment->object_key))->toBe($png);
});

test('Telegram 入站文档带 caption 时额外落文本消息', function () {
    Storage::fake('local');
    Http::fake([
        '*/getFile' => Http::response(['ok' => true, 'result' => ['file_path' => 'documents/file_2.pdf']]),
        '*/file/bot*' => Http::response('PDF-BYTES'),
        '*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
    ]);

    $channel = makeMediaTelegramChannel();

    postTelegramUpdate($channel, [
        'message_id' => 9002,
        'from' => ['id' => 77002, 'first_name' => '访客'],
        'chat' => ['id' => 77002, 'type' => 'private'],
        'document' => ['file_id' => 'F2', 'file_name' => 'report.pdf', 'mime_type' => 'application/pdf'],
        'caption' => '麻烦看下这个报告',
    ])->assertOk();

    $conversation = Conversation::query()->where('channel_id', $channel->id)->firstOrFail();

    $fileMessage = ConversationMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('kind', MessageKind::File)
        ->first();
    expect($fileMessage)->not->toBeNull();

    // caption 作为独立文本消息落库，并作为可唤起 AI 的返回值。
    $captionMessage = ConversationMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('kind', MessageKind::Text)
        ->where('role', MessageRole::Visitor)
        ->first();
    expect($captionMessage)->not->toBeNull()
        ->and($captionMessage->content)->toBe('麻烦看下这个报告');

    $attachment = Attachment::query()
        ->where('attachable_id', $fileMessage->getKey())
        ->first();
    expect($attachment->purpose)->toBe(AttachmentPurpose::ConversationFile)
        ->and($attachment->original_name)->toBe('report.pdf');
});

test('Telegram 入站媒体对同一 message_id 幂等', function () {
    Storage::fake('local');
    $png = telegramInboundPngBytes();
    Http::fake([
        '*/getFile' => Http::response(['ok' => true, 'result' => ['file_path' => 'photos/file_3.jpg']]),
        '*/file/bot*' => Http::response($png),
        '*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
    ]);

    $channel = makeMediaTelegramChannel();
    $message = [
        'message_id' => 9003,
        'from' => ['id' => 77003, 'first_name' => '访客'],
        'chat' => ['id' => 77003, 'type' => 'private'],
        'photo' => [['file_id' => 'F3']],
    ];

    postTelegramUpdate($channel, $message)->assertOk();
    postTelegramUpdate($channel, $message)->assertOk();

    expect(ConversationMessage::query()->where('client_msg_id', 'tg_9003')->count())->toBe(1)
        ->and(Attachment::query()->count())->toBe(1);
});

test('Telegram 出站图片消息调用 sendPhoto 上传附件', function () {
    Storage::fake('local');
    Http::fake([
        '*/sendPhoto' => Http::response(['ok' => true, 'result' => ['message_id' => 9300]]),
        '*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
    ]);
    // 防止创建钩子的发送任务在附件就绪前同步执行。
    Queue::fake();

    $channel = makeMediaTelegramChannel();
    $context = app(ResolveTelegramReceptionContextAction::class)->handle($channel->code, '88001', '访客');
    $conversation = $context['conversation'];

    $imageMessage = ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Teammate,
        'sender_name' => '客服',
        'kind' => MessageKind::Image,
        'content' => null,
    ]);

    // 落一张图片附件并绑定到该消息。
    $objectKey = 'conversations/'.Str::ulid().'.jpg';
    $attachment = Attachment::query()->create([
        'storage_profile_id' => StorageProfile::factory()->create()->id,
        'object_key' => $objectKey,
        'original_name' => 'photo.jpg',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'byte_size' => strlen('OUTBOUND-IMG'),
        'purpose' => AttachmentPurpose::ConversationImage,
        'status' => AttachmentStatus::Attached,
        'attachable_type' => $imageMessage->getMorphClass(),
        'attachable_id' => $imageMessage->getKey(),
        'metadata' => [],
        'uploaded_at' => now(),
        'attached_at' => now(),
    ]);
    $attachment->filesystem()->put($attachment->object_key, 'OUTBOUND-IMG');

    (new SendTelegramMessageJob((string) $imageMessage->id))->handle(app(TelegramBotApi::class), app(TelegramHtmlConverter::class));

    expect($imageMessage->fresh()->delivery_status)->toBe(MessageDeliveryStatus::Sent)
        ->and($imageMessage->fresh()->payload['telegram']['message_id'] ?? null)->toBe(9300);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/sendPhoto'));
});
