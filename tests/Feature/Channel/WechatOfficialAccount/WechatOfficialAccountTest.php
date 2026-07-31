<?php

use App\Actions\Channel\WechatOfficialAccount\CreateWechatOfficialAccountChannelAction;
use App\Actions\Channel\WechatOfficialAccount\ReconcileWechatInboundMessagesAction;
use App\Actions\Channel\WechatOfficialAccount\ShowWechatOfficialAccountChannelDetailPageAction;
use App\Actions\Channel\WechatOfficialAccount\UpdateWechatOfficialAccountChannelBasicAction;
use App\Actions\Inbox\RetryInboxConversationMessageAction;
use App\Actions\Reception\AppendWechatOfficialAccountVisitorImageAction;
use App\Actions\Reception\AppendWechatOfficialAccountVisitorMessageAction;
use App\Data\Channel\WechatOfficialAccount\ChannelWechatOfficialAccountSettingsData;
use App\Data\Channel\WechatOfficialAccount\FormCreateWechatOfficialAccountChannelData;
use App\Data\Channel\WechatOfficialAccount\FormUpdateWechatOfficialAccountChannelBasicData;
use App\Enums\AttachmentPurpose;
use App\Enums\AttachmentStatus;
use App\Enums\ContactSource;
use App\Enums\ConversationEntryMode;
use App\Enums\ConversationInboxStatus;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageKind;
use App\Enums\MessageOutboxStatus;
use App\Enums\MessageRole;
use App\Enums\ReceptionLanguage;
use App\Enums\UserPermission;
use App\Enums\WechatInboundMessageStatus;
use App\Exceptions\WechatApiException;
use App\Jobs\WechatOfficialAccount\ProcessWechatOfficialAccountMessageJob;
use App\Jobs\WechatOfficialAccount\SendWechatOfficialAccountMessageJob;
use App\Models\Attachment;
use App\Models\Channel;
use App\Models\ContactIdentity;
use App\Models\ConversationMessage;
use App\Models\ReceptionPlan;
use App\Models\ReceptionPlanVersion;
use App\Models\StorageProfile;
use App\Models\User;
use App\Models\WechatInboundMessage;
use App\Services\Reception\ReceptionPipelineDispatcher;
use App\Services\Wechat\WechatOfficialAccountApi;
use App\Services\Wechat\WechatOfficialAccountApplicationFactory;
use EasyWeChat\Kernel\HttpClient\AccessTokenAwareClient;
use EasyWeChat\OfficialAccount\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

/**
 * 返回可被真实内容检测识别的 PNG 字节。
 */
function wechatInboundPngBytes(): string
{
    return (string) base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);
}

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
    config(['app.url' => 'https://helmdesk.test']);
});

function createWechatDeployablePlanVersion(): ReceptionPlanVersion
{
    makeAiModel();
    $plan = ReceptionPlan::factory()->create(['name' => '微信接待方案-'.Str::lower(Str::random(6))]);

    return ReceptionPlanVersion::factory()->for($plan, 'plan')->create();
}

function makeInboundWechatChannel(): Channel
{
    $version = createWechatDeployablePlanVersion();

    return Channel::factory()->wechatOfficialAccount()->create([
        'reception_plan_id' => $version->reception_plan_id,
        'settings' => ChannelWechatOfficialAccountSettingsData::from([
            'app_id' => 'wx'.Str::lower(Str::random(16)),
            'app_secret' => Str::random(32),
            'token' => Str::random(24),
        ]),
    ]);
}

function wechatSignature(Channel $channel, string $timestamp, string $nonce): string
{
    $parts = [$channel->settings->token, $timestamp, $nonce];
    sort($parts, SORT_STRING);

    return sha1(implode('', $parts));
}

test('微信公众号明文 webhook 验签后立即入队', function () {
    Queue::fake();
    $channel = makeInboundWechatChannel();
    $timestamp = '1710000000';
    $nonce = 'wechat-nonce';
    $xml = '<xml><ToUserName><![CDATA['.$channel->settings->app_id.']]></ToUserName><FromUserName><![CDATA[openid-1001]]></FromUserName><CreateTime>1710000000</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA[你好]]></Content><MsgId>70001</MsgId></xml>';

    $this->call(
        'POST',
        "/webhook/wechat/{$channel->code}?signature=".wechatSignature($channel, $timestamp, $nonce)."&timestamp={$timestamp}&nonce={$nonce}",
        [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml,
    )->assertOk()->assertContent('success');

    $inbound = WechatInboundMessage::query()->where('provider_message_id', '70001')->firstOrFail();
    expect($inbound->channel_id)->toBe((string) $channel->id);
    Queue::assertPushed(ProcessWechatOfficialAccountMessageJob::class, fn (ProcessWechatOfficialAccountMessageJob $job): bool => $job->inboundMessageId === (string) $inbound->id);
});

test('微信公众号重复 webhook 只创建一条入站台账', function () {
    Queue::fake();
    $channel = makeInboundWechatChannel();
    $timestamp = '1710000001';
    $nonce = 'wechat-duplicate';
    $xml = '<xml><ToUserName><![CDATA['.$channel->settings->app_id.']]></ToUserName><FromUserName><![CDATA[openid-1002]]></FromUserName><CreateTime>1710000001</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA[重复消息]]></Content><MsgId>70002</MsgId></xml>';
    $url = "/webhook/wechat/{$channel->code}?signature=".wechatSignature($channel, $timestamp, $nonce)."&timestamp={$timestamp}&nonce={$nonce}";

    $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml)->assertOk();
    $this->call('POST', $url, [], [], [], ['CONTENT_TYPE' => 'text/xml'], $xml)->assertOk();

    expect(WechatInboundMessage::query()->where('provider_message_id', '70002')->count())->toBe(1);
    Queue::assertPushed(ProcessWechatOfficialAccountMessageJob::class, 1);
});

test('微信公众号入站补偿在派发前预占台账避免重复入队', function () {
    Queue::fake();
    $channel = makeInboundWechatChannel();
    $inbound = WechatInboundMessage::query()->create([
        'channel_id' => $channel->id,
        'provider_message_id' => '70002-reconcile',
        'message_type' => 'text',
        'payload' => ['MsgType' => 'text'],
        'available_at' => now(),
    ]);

    expect(ReconcileWechatInboundMessagesAction::run())->toBe(1)
        ->and(ReconcileWechatInboundMessagesAction::run())->toBe(0)
        ->and($inbound->fresh()->available_at?->isFuture())->toBeTrue();
    Queue::assertPushed(ProcessWechatOfficialAccountMessageJob::class, 1);
    $job = Queue::pushed(ProcessWechatOfficialAccountMessageJob::class)->first();
    expect($job->reservationToken)->not->toBeNull()
        ->and($inbound->fresh()->claimForProcessing($job->reservationToken))->not->toBeNull();
});

test('微信公众号图片入站持久化为会话附件并进入已处理状态', function () {
    Queue::fake();
    fakeAttachmentStorage();
    $channel = makeInboundWechatChannel();
    $inbound = WechatInboundMessage::query()->create([
        'channel_id' => $channel->id,
        'provider_message_id' => '70003',
        'message_type' => 'image',
        'payload' => [
            'MsgType' => 'image',
            'MsgId' => '70003',
            'FromUserName' => 'openid-image',
            'MediaId' => 'media-image-1',
        ],
        'available_at' => now(),
    ]);
    $png = wechatInboundPngBytes();
    $api = Mockery::mock(WechatOfficialAccountApi::class);
    $api->shouldReceive('downloadImage')->once()->andReturn([
        'contents' => $png,
        'mime_type' => 'image/jpeg',
        'file_name' => 'wechat-image.jpg',
    ]);

    (new ProcessWechatOfficialAccountMessageJob((string) $inbound->id))->handle(
        app(AppendWechatOfficialAccountVisitorMessageAction::class),
        app(AppendWechatOfficialAccountVisitorImageAction::class),
        app(ReceptionPipelineDispatcher::class),
        $api,
    );

    $message = ConversationMessage::query()->where('client_msg_id', 'wxoa_'.$channel->code.'_70003')->firstOrFail();
    $attachment = Attachment::query()->where('attachable_id', $message->id)->firstOrFail();
    expect($inbound->fresh()->status)->toBe(WechatInboundMessageStatus::Processed)
        ->and($message->kind)->toBe(MessageKind::Image)
        ->and($attachment->mime_type)->toBe('image/png')
        ->and($attachment->filesystem()->get($attachment->object_key))->toBe($png);
});

test('微信公众号事件入站静默完成且不发送不支持提示', function () {
    Queue::fake();
    $channel = makeInboundWechatChannel();
    $inbound = WechatInboundMessage::query()->create([
        'channel_id' => $channel->id,
        'provider_message_id' => 'event-subscribe-1',
        'message_type' => 'event',
        'payload' => [
            'MsgType' => 'event',
            'FromUserName' => 'openid-event',
            'Event' => 'subscribe',
        ],
        'available_at' => now(),
    ]);
    $api = Mockery::mock(WechatOfficialAccountApi::class);
    $api->shouldNotReceive('sendText');

    (new ProcessWechatOfficialAccountMessageJob((string) $inbound->id))->handle(
        app(AppendWechatOfficialAccountVisitorMessageAction::class),
        app(AppendWechatOfficialAccountVisitorImageAction::class),
        app(ReceptionPipelineDispatcher::class),
        $api,
    );

    expect($inbound->fresh()->status)->toBe(WechatInboundMessageStatus::Processed);
});

test('微信公众号不支持的主动消息会发送类型提示', function () {
    Queue::fake();
    $channel = makeInboundWechatChannel();
    $inbound = WechatInboundMessage::query()->create([
        'channel_id' => $channel->id,
        'provider_message_id' => 'voice-unsupported-1',
        'message_type' => 'voice',
        'payload' => [
            'MsgType' => 'voice',
            'FromUserName' => 'openid-voice',
            'MsgId' => 'voice-unsupported-1',
        ],
        'available_at' => now(),
    ]);
    $api = Mockery::mock(WechatOfficialAccountApi::class);
    $api->shouldReceive('sendText')->once()->withArgs(
        fn (Channel $actual, string $openid): bool => $actual->is($channel) && $openid === 'openid-voice',
    );

    (new ProcessWechatOfficialAccountMessageJob((string) $inbound->id))->handle(
        app(AppendWechatOfficialAccountVisitorMessageAction::class),
        app(AppendWechatOfficialAccountVisitorImageAction::class),
        app(ReceptionPipelineDispatcher::class),
        $api,
    );

    expect($inbound->fresh()->status)->toBe(WechatInboundMessageStatus::Processed);
});

test('未配置凭证的微信公众号拒绝 webhook', function () {
    Queue::fake();
    $version = createWechatDeployablePlanVersion();
    $channel = app(CreateWechatOfficialAccountChannelAction::class)->handle(new FormCreateWechatOfficialAccountChannelData(
        name: '未配置公众号',
        reception_plan_id: (string) $version->reception_plan_id,
    ));

    $this->post("/webhook/wechat/{$channel->code}?signature=x&timestamp=1&nonce=2")
        ->assertForbidden();
    Queue::assertNothingPushed();
});

test('微信公众号访客消息按 MsgId 幂等创建会话和渠道身份', function () {
    Queue::fake();
    $channel = makeInboundWechatChannel();
    $args = [$channel->code, 'openid-2001', '查询物流', '80001', '微信访客', 'zh_CN'];

    $first = app(AppendWechatOfficialAccountVisitorMessageAction::class)->handle(...$args);
    $second = app(AppendWechatOfficialAccountVisitorMessageAction::class)->handle(...$args);

    expect($second['message']->id)->toBe($first['message']->id)
        ->and($first['conversation']->entry_mode)->toBe(ConversationEntryMode::WechatOfficialAccount)
        ->and(ConversationMessage::query()->where('client_msg_id', 'wxoa_'.$channel->code.'_80001')->count())->toBe(1)
        ->and(ContactIdentity::query()->where('namespace', 'wechat_oa:'.$channel->code.':'.$channel->settings->app_id)->firstOrFail()->contact->source)
        ->toBe(ContactSource::WechatOfficialAccount);
});

test('微信公众号文本出站通过 Outbox 发送并回写状态', function () {
    Queue::fake();
    $channel = makeInboundWechatChannel();
    $inbound = app(AppendWechatOfficialAccountVisitorMessageAction::class)->handle($channel->code, 'openid-3001', '你好', '80002');
    $ai = ConversationMessage::query()->create([
        'conversation_id' => $inbound['conversation']->id,
        'role' => MessageRole::Ai,
        'kind' => MessageKind::Text,
        'content' => '您好，已收到。',
        'sender_name' => 'AI',
    ]);

    Queue::assertPushed(SendWechatOfficialAccountMessageJob::class);
    $api = Mockery::mock(WechatOfficialAccountApi::class);
    $api->shouldReceive('sendText')->once();
    (new SendWechatOfficialAccountMessageJob((string) $ai->id))->handle($api);

    expect($ai->fresh()->delivery_status)->toBe(MessageDeliveryStatus::Sent)
        ->and($ai->fresh()->outbox?->status)->toBe(MessageOutboxStatus::Sent);
});

test('微信公众号 IP 白名单错误直接标记投递失败', function () {
    Queue::fake();
    $channel = makeInboundWechatChannel();
    $inbound = app(AppendWechatOfficialAccountVisitorMessageAction::class)->handle($channel->code, 'openid-3004', '你好', '80005');
    $ai = ConversationMessage::query()->create([
        'conversation_id' => $inbound['conversation']->id,
        'role' => MessageRole::Ai,
        'kind' => MessageKind::Text,
        'content' => '您好，已收到。',
        'sender_name' => 'AI',
    ]);

    $exception = WechatApiException::fromResult([
        'errcode' => 40164,
        'errmsg' => 'invalid ip, not in whitelist',
    ], '微信公众号客服消息发送失败。');
    $api = Mockery::mock(WechatOfficialAccountApi::class);
    $api->shouldReceive('sendText')->once()->andThrow($exception);

    (new SendWechatOfficialAccountMessageJob((string) $ai->id))->handle($api);

    expect($exception->errorCode)->toBe(40164)
        ->and($exception->isRetryable())->toBeFalse()
        ->and($ai->fresh()->delivery_status)->toBe(MessageDeliveryStatus::Failed)
        ->and($ai->fresh()->outbox?->status)->toBe(MessageOutboxStatus::Failed)
        ->and($ai->fresh()->outbox?->last_error)->toContain('40164');
});

test('微信公众号长文本按 UTF-8 字节边界分片发送', function () {
    Queue::fake();
    $channel = makeInboundWechatChannel();
    $inbound = app(AppendWechatOfficialAccountVisitorMessageAction::class)->handle($channel->code, 'openid-3002', '你好', '80003');
    $ai = ConversationMessage::query()->create([
        'conversation_id' => $inbound['conversation']->id,
        'role' => MessageRole::Ai,
        'kind' => MessageKind::Text,
        'content' => str_repeat('中', 700),
        'sender_name' => 'AI',
    ]);

    $api = Mockery::mock(WechatOfficialAccountApi::class);
    $api->shouldReceive('sendText')->twice()->withArgs(fn (Channel $actual, string $openid, string $content): bool => $actual->is($channel) && $openid === 'openid-3002' && strlen($content) <= WechatOfficialAccountApi::MAX_TEXT_BYTES
    );
    (new SendWechatOfficialAccountMessageJob((string) $ai->id))->handle($api);

    expect($ai->fresh()->delivery_status)->toBe(MessageDeliveryStatus::Sent)
        ->and($ai->fresh()->outbox?->payload['wechat_oa']['chunk_count'] ?? null)->toBe(2);
});

test('微信公众号不支持的文件出站明确标记失败', function () {
    Queue::fake();
    $channel = makeInboundWechatChannel();
    $inbound = app(AppendWechatOfficialAccountVisitorMessageAction::class)->handle($channel->code, 'openid-3003', '你好', '80004');
    $file = ConversationMessage::query()->create([
        'conversation_id' => $inbound['conversation']->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::File,
        'sender_name' => '客服',
    ]);

    expect($file->fresh()->delivery_status)->toBe(MessageDeliveryStatus::Failed)
        ->and($file->fresh()->outbox?->status)->toBe(MessageOutboxStatus::Failed);
    Queue::assertNotPushed(SendWechatOfficialAccountMessageJob::class);
});

test('微信公众号图片出站上传临时素材并更新投递状态', function () {
    Queue::fake();
    fakeAttachmentStorage();
    $channel = makeInboundWechatChannel();
    $inbound = app(AppendWechatOfficialAccountVisitorMessageAction::class)->handle($channel->code, 'openid-image-out', '你好', '80005');
    $image = ConversationMessage::query()->create([
        'conversation_id' => $inbound['conversation']->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Image,
        'sender_name' => '客服',
    ]);
    $attachment = Attachment::query()->create([
        'storage_profile_id' => StorageProfile::factory()->create()->id,
        'object_key' => 'wechat/outbound.jpg',
        'original_name' => 'outbound.jpg',
        'mime_type' => 'image/jpeg',
        'extension' => 'jpg',
        'byte_size' => strlen('OUTBOUND-WECHAT-IMAGE'),
        'purpose' => AttachmentPurpose::ConversationImage,
        'status' => AttachmentStatus::Attached,
        'attachable_type' => $image->getMorphClass(),
        'attachable_id' => $image->id,
        'uploaded_at' => now(),
        'attached_at' => now(),
    ]);
    $attachment->filesystem()->put($attachment->object_key, 'OUTBOUND-WECHAT-IMAGE');
    $api = Mockery::mock(WechatOfficialAccountApi::class);
    $api->shouldReceive('sendImage')
        ->once()
        ->withArgs(fn (Channel $actual, string $openid, string $contents, string $fileName): bool => $actual->is($channel)
            && $openid === 'openid-image-out'
            && $contents === 'OUTBOUND-WECHAT-IMAGE'
            && $fileName === 'outbound.jpg')
        ->andReturn('wechat-media-out');

    (new SendWechatOfficialAccountMessageJob((string) $image->id))->handle($api);

    expect($image->fresh()->delivery_status)->toBe(MessageDeliveryStatus::Sent)
        ->and($image->fresh()->outbox?->provider_message_id)->toBe('wechat-media-out');
});

test('微信公众号图片以 multipart 表单上传临时素材', function () {
    $channel = makeInboundWechatChannel();
    $requests = [];
    $httpClient = new MockHttpClient(
        function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = compact('method', 'url', 'options');

            return new MockResponse(
                count($requests) === 1 ? '{"media_id":"wechat-media-id"}' : '{"errcode":0}',
                ['response_headers' => ['content-type: application/json']],
            );
        },
        'https://api.weixin.qq.com/',
    );
    $client = new AccessTokenAwareClient($httpClient);
    $application = Mockery::mock(Application::class);
    $application->shouldReceive('getClient')->twice()->andReturn($client);
    $factory = Mockery::mock(WechatOfficialAccountApplicationFactory::class);
    $factory->shouldReceive('make')->twice()->with($channel)->andReturn($application);

    $mediaId = (new WechatOfficialAccountApi($factory))->sendImage(
        $channel,
        'openid-multipart',
        'MULTIPART-IMAGE-CONTENTS',
        'multipart.jpg',
    );

    $upload = array_first($requests);
    expect($mediaId)->toBe('wechat-media-id')
        ->and(strtolower(implode(' ', $upload['options']['headers'])))->toContain('multipart/form-data')
        ->and($upload['options']['body'])->toBeString()
        ->and($upload['options']['body'])->toContain('name="media"')
        ->and($upload['options']['body'])->toContain('filename="multipart.jpg"')
        ->and($upload['options']['body'])->toContain('MULTIPART-IMAGE-CONTENTS');
});

test('客服可以重新投递微信公众号失败消息', function () {
    Queue::fake();
    $channel = makeInboundWechatChannel();
    $inbound = app(AppendWechatOfficialAccountVisitorMessageAction::class)->handle($channel->code, 'openid-retry', '你好', '80006');
    $conversation = $inbound['conversation'];
    $conversation->update([
        'assigned_user_id' => $this->user->id,
        'inbox_status' => ConversationInboxStatus::TeammateHandling,
    ]);
    $message = ConversationMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => '需要重试',
        'sender_name' => '客服',
    ]);
    $message->outbox->failIfUnsent('临时配置错误');
    $message->update(['delivery_status' => MessageDeliveryStatus::Failed]);
    Queue::fake();

    app(RetryInboxConversationMessageAction::class)->handle($this->user, (string) $conversation->id, (string) $message->id);
    app(RetryInboxConversationMessageAction::class)->handle($this->user, (string) $conversation->id, (string) $message->id);

    expect($message->fresh()->delivery_status)->toBe(MessageDeliveryStatus::Sending)
        ->and($message->fresh()->outbox?->status)->toBe(MessageOutboxStatus::Pending);
    Queue::assertPushed(SendWechatOfficialAccountMessageJob::class, 1);
});

test('微信公众号创建后在详情页配置直连凭证', function () {
    $version = createWechatDeployablePlanVersion();
    $channel = app(CreateWechatOfficialAccountChannelAction::class)->handle(new FormCreateWechatOfficialAccountChannelData(
        name: '待配置公众号',
        reception_plan_id: (string) $version->reception_plan_id,
        default_visitor_locale: ReceptionLanguage::ChineseSimplified,
    ));

    expect($channel->code)->toStartWith('wxoa_')
        ->and($channel->settings->isConfigured())->toBeFalse();

    app(UpdateWechatOfficialAccountChannelBasicAction::class)->handle($channel, new FormUpdateWechatOfficialAccountChannelBasicData(
        name: $channel->name,
        app_id: 'wx-updated-app-id',
        app_secret: 'updated-app-secret',
        token: 'updated-token',
        message_mode: 'plain',
        reception_plan_id: (string) $channel->reception_plan_id,
        default_visitor_locale: ReceptionLanguage::ChineseSimplified,
        aes_key: str_repeat('a', 43),
        visitor_message_ai_translation_enabled: true,
        translation_context_hint: '保留业务术语。',
    ));

    $props = app(ShowWechatOfficialAccountChannelDetailPageAction::class)->handle($this->user, (string) $channel->id);
    expect($channel->fresh()->settings->aes_key)->toBe('')
        ->and($props->wechat_channel->app_secret)->toBe('updated-app-secret')
        ->and($props->wechat_channel->visitor_message_ai_translation_enabled)->toBeTrue()
        ->and($props->wechat_channel->translation_context_hint)->toBe('保留业务术语。');
});

test('微信公众号只读成员不能读取接入密钥', function () {
    $channel = makeInboundWechatChannel();
    $viewer = User::factory()->create([
        'permissions' => [UserPermission::ChannelsView->value],
    ]);
    attachMember($viewer);

    $this->actingAs($viewer)
        ->get(route('app.manage.channels.wechat-official-account.show', ['channel' => $channel->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('wechat_channel.app_secret', null)
            ->where('wechat_channel.token', null)
            ->where('wechat_channel.aes_key', null)
        );
});
