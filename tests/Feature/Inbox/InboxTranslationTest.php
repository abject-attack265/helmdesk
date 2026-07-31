<?php

use App\Actions\Inbox\ReplyInboxConversationAction;
use App\Actions\Inbox\TranslateInboxConversationMessagesAction;
use App\Actions\Inbox\TranslateInboxConversationPreviewsAction;
use App\Data\Inbox\FormReplyInboxConversationData;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Exceptions\BusinessException;
use App\Jobs\Translation\TranslateInboxConversationMessagesJob;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use App\Services\Translation\TranslationProviderPool;
use App\Services\Translation\TranslationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = $this->createUserWithInstance(['locale' => 'zh-CN']);
});

/** 创建一条可由当前客服回复的英文访客会话。 */
function makeInboxTranslationConversation(User $user): Conversation
{
    $channel = Channel::factory()->create();
    $contact = Contact::factory()->create();

    return Conversation::factory()
        ->forContactChannel($contact, $channel)
        ->assignedTo($user)
        ->create([
            'visitor_locale' => 'en',
            'status' => ConversationStatus::Open,
            'inbox_status' => ConversationInboxStatus::TeammateHandling,
        ]);
}

it('翻译发送时正文保存访客可见内容并保留客服原文', function () {
    $conversation = makeInboxTranslationConversation($this->user);

    $this->actingAs($this->user)
        ->post(route('app.inbox.conversations.reply', ['conversation' => $conversation->id]), [
            'content' => '我马上帮您查询。',
            'visitor_content' => 'I will check that for you right away.',
            'visitor_locale' => 'en',
            'source_locale' => 'zh-CN',
        ])
        ->assertRedirect();

    $message = ConversationMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('role', MessageRole::Teammate)
        ->firstOrFail();

    expect($message->content)->toBe('I will check that for you right away.')
        ->and($message->content_locale)->toBe('en')
        ->and($message->payload['translations']['zh-CN']['text'])->toBe('我马上帮您查询。')
        ->and($message->payload['translations']['zh-CN']['provider_slug'])->toBe('author');
});

it('翻译发送按实际输入语言保存客服原文', function () {
    $this->user->update(['locale' => 'en']);
    $conversation = makeInboxTranslationConversation($this->user);

    $this->actingAs($this->user)
        ->post(route('app.inbox.conversations.reply', ['conversation' => $conversation->id]), [
            'content' => 'すぐに確認します。',
            'visitor_content' => 'I will check that right away.',
            'visitor_locale' => 'en',
            'source_locale' => 'JA',
        ])
        ->assertRedirect();

    $message = ConversationMessage::query()
        ->where('conversation_id', $conversation->id)
        ->where('role', MessageRole::Teammate)
        ->firstOrFail();

    expect($message->payload['translations']['JA']['text'])->toBe('すぐに確認します。')
        ->and($message->payload['translations']['JA']['target_lang'])->toBe('JA');
});

it('翻译发送要求访客内容和语言字段同时提交', function () {
    $conversation = makeInboxTranslationConversation($this->user);

    $this->actingAs($this->user)
        ->post(route('app.inbox.conversations.reply', ['conversation' => $conversation->id]), [
            'content' => '我马上帮您查询。',
            'visitor_content' => 'I will check that for you right away.',
        ])
        ->assertSessionHasErrors(['visitor_locale', 'source_locale']);

    expect(ConversationMessage::query()->where('conversation_id', $conversation->id)->exists())->toBeFalse();
});

it('发送前访客语言变化时拒绝旧译文', function () {
    $conversation = makeInboxTranslationConversation($this->user);

    $data = new FormReplyInboxConversationData(
        content: '我马上帮您查询。',
        visitor_content: 'すぐに確認します。',
        visitor_locale: 'ja',
        source_locale: 'zh-CN',
    );

    expect(fn () => app(ReplyInboxConversationAction::class)->handle($this->user, $conversation->id, $data))
        ->toThrow(BusinessException::class);
});

it('客服账号语言与访客语言一致时仍识别实际输入语言', function () {
    $this->user->update(['locale' => 'en']);
    $conversation = makeInboxTranslationConversation($this->user);

    $this->mock(TranslationProviderPool::class, function ($mock): void {
        $mock->shouldReceive('translate')
            ->once()
            ->andReturn(new TranslationResult(
                text: 'I will check that for you.',
                source_lang: 'zh-CN',
                target_lang: 'en',
                provider_slug: 'deepseek-test',
                model: 'deepseek-v4-flash',
                latency_ms: 1,
                char_count: 9,
            ));
    });

    $this->actingAs($this->user)
        ->postJson(route('app.inbox.conversations.reply.translation-preview', [
            'conversation' => $conversation->id,
        ]), ['content' => '我马上帮您查询。'])
        ->assertOk()
        ->assertJson([
            'visitor_content' => 'I will check that for you.',
            'visitor_locale' => 'en',
            'source_locale' => 'zh-CN',
        ]);
});

it('回复翻译预览拒绝不是负责人的客服', function () {
    $owner = User::factory()->create();
    $conversation = makeInboxTranslationConversation($owner);

    $this->actingAs($this->user)
        ->postJson(route('app.inbox.conversations.reply.translation-preview', [
            'conversation' => $conversation->id,
        ]), ['content' => '我马上帮您查询。'])
        ->assertForbidden()
        ->assertJson([
            'message' => __('conversation.errors.reply_not_allowed_for_assignee'),
        ]);
});

it('回复翻译预览原样返回不含语言文字的内容', function () {
    $conversation = makeInboxTranslationConversation($this->user);
    $this->mock(TranslationProviderPool::class, function ($mock): void {
        $mock->shouldNotReceive('translate');
    });

    $this->actingAs($this->user)
        ->postJson(route('app.inbox.conversations.reply.translation-preview', [
            'conversation' => $conversation->id,
        ]), ['content' => '￥123.45 🎉'])
        ->assertOk()
        ->assertJson([
            'visitor_content' => '￥123.45 🎉',
            'visitor_locale' => 'en',
            'source_locale' => 'zh-CN',
        ]);
});

it('按显式语言翻译有效文本并跳过撤回和非文本消息', function () {
    $conversation = makeInboxTranslationConversation($this->user);

    $this->mock(TranslationProviderPool::class, function ($mock): void {
        $mock->shouldReceive('hasUsable')->once()->andReturnTrue();
        $mock->shouldReceive('translate')->times(3)->andReturnUsing(
            function (string $content, string $source, string $target): TranslationResult {
                expect($source)->toBe('en')
                    ->and($target)->toBe('zh-CN');

                return new TranslationResult(
                    text: '译:'.$content,
                    source_lang: $source,
                    target_lang: $target,
                    provider_slug: 'deepseek-test',
                    model: 'deepseek-v4-flash',
                    latency_ms: 1,
                    char_count: mb_strlen($content),
                );
            },
        );
    });

    $visitorMessage = ConversationMessage::factory()->forConversation($conversation)->visitorText()->create([
        'content' => 'Visitor text',
    ]);
    $aiMessage = ConversationMessage::factory()->forConversation($conversation)->aiText()->create([
        'content' => 'AI text',
    ]);
    $ownMessage = ConversationMessage::factory()->forConversation($conversation)->create([
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => 'Agent text',
        'sender_user_id' => $this->user->id,
    ]);
    $recalledMessage = ConversationMessage::factory()->forConversation($conversation)->visitorText()->create([
        'content' => 'Recalled text',
        'recalled_at' => now(),
    ]);
    $imageMessage = ConversationMessage::factory()->forConversation($conversation)->create([
        'role' => MessageRole::Visitor,
        'kind' => MessageKind::Image,
        'content' => 'Image caption',
    ]);

    $translated = TranslateInboxConversationMessagesAction::run(
        conversationId: (string) $conversation->id,
        messageIds: [
            (string) $visitorMessage->id,
            (string) $aiMessage->id,
            (string) $ownMessage->id,
            (string) $recalledMessage->id,
            (string) $imageMessage->id,
        ],
        targetLocale: 'zh-CN',
        sourceLocale: 'en',
        force: true,
    );

    expect($translated)->toBe(3)
        ->and($visitorMessage->refresh()->payload['translations']['zh-CN']['text'])->toBe('译:Visitor text')
        ->and($aiMessage->refresh()->payload['translations']['zh-CN']['text'])->toBe('译:AI text')
        ->and($ownMessage->refresh()->payload['translations']['zh-CN']['text'])->toBe('译:Agent text')
        ->and($recalledMessage->refresh()->payload['translations'] ?? null)->toBeNull()
        ->and($imageMessage->refresh()->payload['translations'] ?? null)->toBeNull();
});

it('消息翻译接口把源语言和目标语言传入异步任务', function () {
    Bus::fake();
    $conversation = makeInboxTranslationConversation($this->user);
    $message = ConversationMessage::factory()->forConversation($conversation)->visitorText()->create([
        'content' => 'Hello',
    ]);

    $this->actingAs($this->user)
        ->postJson(route('app.inbox.conversations.messages.translate', [
            'conversation' => $conversation->id,
        ]), [
            'message_ids' => [(string) $message->id],
            'source_locale' => 'en',
            'target_locale' => 'zh-CN',
        ])
        ->assertOk()
        ->assertJson(['queued' => true]);

    Bus::assertDispatched(
        TranslateInboxConversationMessagesJob::class,
        fn (TranslateInboxConversationMessagesJob $job): bool => $job->conversationId === (string) $conversation->id
            && $job->messageIds === [(string) $message->id]
            && $job->sourceLocale === 'en'
            && $job->targetLocale === 'zh-CN',
    );
});

it('会话列表预览按目标语言翻译最后一条消息', function () {
    $conversation = makeInboxTranslationConversation($this->user);

    $this->mock(TranslationProviderPool::class, function ($mock): void {
        $mock->shouldReceive('hasUsable')->once()->andReturnTrue();
        $mock->shouldReceive('translate')->once()->andReturnUsing(
            function (string $content, string $source, string $target): TranslationResult {
                expect($source)->toBe('zh-CN')
                    ->and($target)->toBe('en');

                return new TranslationResult(
                    text: 'Translated preview',
                    source_lang: $source,
                    target_lang: $target,
                    provider_slug: 'deepseek-test',
                    model: 'deepseek-v4-flash',
                    latency_ms: 1,
                    char_count: mb_strlen($content),
                );
            },
        );
    });

    $lastMessage = ConversationMessage::factory()->forConversation($conversation)->create([
        'role' => MessageRole::Teammate,
        'kind' => MessageKind::Text,
        'content' => '需要退款',
        'sender_user_id' => $this->user->id,
    ]);

    $translated = TranslateInboxConversationPreviewsAction::run(
        conversationIds: [(string) $conversation->id],
        targetLocale: 'en',
        sourceLocale: 'zh-CN',
    );

    expect($translated)->toBe(1)
        ->and($lastMessage->refresh()->payload['translations']['en']['text'])->toBe('Translated preview');
});
