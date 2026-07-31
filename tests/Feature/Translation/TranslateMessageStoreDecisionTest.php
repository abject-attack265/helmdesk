<?php

use App\Actions\Translation\TranslateConversationMessageAction;
use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Enums\TranslationProviderSelectionStrategy;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Translation\TranslationProviderPool;
use App\Services\Translation\TranslationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 注册返回固定译文的翻译池。
 */
function bindMessageTranslationPool(string $translated): void
{
    $pool = Mockery::mock(TranslationProviderPool::class);
    $pool->shouldReceive('translate')->once()->andReturn(new TranslationResult(
        text: $translated,
        source_lang: 'zh',
        target_lang: 'en',
        provider_slug: 'deepseek-test',
        model: 'deepseek-v4-flash',
        latency_ms: 1,
        char_count: mb_strlen($translated),
    ));
    app()->instance(TranslationProviderPool::class, $pool);
}

/**
 * 创建一条原文语言未知的访客消息。
 *
 * @return array{0: ConversationMessage, 1: Conversation}
 */
function makeTranslatableVisitorMessage(string $content): array
{
    $channel = Channel::factory()->create();
    $conversation = Conversation::factory()->create(['channel_id' => $channel->id]);
    $message = ConversationMessage::factory()->visitorText()->forConversation($conversation)->create([
        'content' => $content,
        'content_locale' => null,
    ]);

    return [$message, $conversation];
}

it('译文与原文相同时只记录原文语言', function () {
    bindMessageTranslationPool('你好');
    [$message, $conversation] = makeTranslatableVisitorMessage('你好');

    app(TranslateConversationMessageAction::class)
        ->handleForTargetLang($message, $conversation, 'en');

    $fresh = $message->fresh();

    expect($fresh->content_locale)->toBe('zh')
        ->and($fresh->payload['translations'] ?? null)->toBeNull();
});

it('不含语言文字的消息不调用翻译供应商', function () {
    $pool = Mockery::mock(TranslationProviderPool::class);
    $pool->shouldNotReceive('translate');
    app()->instance(TranslationProviderPool::class, $pool);
    [$message, $conversation] = makeTranslatableVisitorMessage('￥123.45 🎉');

    $translated = app(TranslateConversationMessageAction::class)
        ->handleForTargetLang($message, $conversation, 'en');

    expect($translated)->toBeFalse()
        ->and($message->fresh()->payload['translations'] ?? null)->toBeNull();
});

it('混合语言产生不同译文时写入目标语言缓存', function () {
    bindMessageTranslationPool('Hello, thanks');
    [$message, $conversation] = makeTranslatableVisitorMessage('你好 thanks');

    app(TranslateConversationMessageAction::class)
        ->handleForTargetLang($message, $conversation, 'en');

    expect($message->fresh()->payload['translations']['en']['text'] ?? null)
        ->toBe('Hello, thanks');
});

it('开启 AI 增强时访客消息优先使用 AI 并读取渠道语境提示', function () {
    $hint = '访客常用 Hinglish，请保留业务术语。';
    $channel = Channel::factory()->create([
        'settings' => ChannelWebSettingsData::defaults([
            'visitor_message_ai_translation_enabled' => true,
            'translation_context_hint' => $hint,
        ]),
    ]);
    $conversation = Conversation::factory()->create(['channel_id' => $channel->id]);
    $message = ConversationMessage::factory()->visitorText()->forConversation($conversation)->create([
        'content' => 'nahi ho raha',
    ]);

    $pool = Mockery::mock(TranslationProviderPool::class);
    $pool->shouldReceive('translate')
        ->once()
        ->withArgs(fn (...$args): bool => ($args[3]['context_hint'] ?? null) === $hint
            && $args[5] === TranslationProviderSelectionStrategy::AiFirst
            && $args[6] === null)
        ->andReturn(new TranslationResult(
            text: '无法完成',
            source_lang: 'hi',
            target_lang: 'zh-CN',
            provider_slug: 'deepseek',
            model: 'deepseek-v4-flash',
            latency_ms: 1,
            char_count: 12,
        ));
    app()->instance(TranslationProviderPool::class, $pool);

    expect(app(TranslateConversationMessageAction::class)
        ->handleForTargetLang($message, $conversation, 'zh-CN'))->toBeTrue();
});

it('关闭 AI 增强时访客消息优先使用机器翻译且不传语境提示', function () {
    $channel = Channel::factory()->create([
        'settings' => ChannelWebSettingsData::defaults([
            'translation_context_hint' => '这段提示不应传给首翻。',
        ]),
    ]);
    $conversation = Conversation::factory()->create(['channel_id' => $channel->id]);
    $message = ConversationMessage::factory()->visitorText()->forConversation($conversation)->create([
        'content' => 'hello',
    ]);

    $pool = Mockery::mock(TranslationProviderPool::class);
    $pool->shouldReceive('translate')
        ->once()
        ->withArgs(fn (...$args): bool => ! isset($args[3]['context_hint'])
            && $args[5] === TranslationProviderSelectionStrategy::MachineFirst
            && $args[6] === null)
        ->andReturn(new TranslationResult(
            text: '你好',
            source_lang: 'en',
            target_lang: 'zh-CN',
            provider_slug: 'google',
            model: null,
            latency_ms: 1,
            char_count: 5,
        ));
    app()->instance(TranslationProviderPool::class, $pool);

    expect(app(TranslateConversationMessageAction::class)
        ->handleForTargetLang($message, $conversation, 'zh-CN'))->toBeTrue();
});

it('开启 AI 增强时非访客消息仍优先使用机器翻译', function () {
    $channel = Channel::factory()->create([
        'settings' => ChannelWebSettingsData::defaults([
            'visitor_message_ai_translation_enabled' => true,
            'translation_context_hint' => '仅用于访客消息。',
        ]),
    ]);
    $conversation = Conversation::factory()->create(['channel_id' => $channel->id]);
    $message = ConversationMessage::factory()->aiText()->forConversation($conversation)->create([
        'content' => 'hello',
    ]);

    $pool = Mockery::mock(TranslationProviderPool::class);
    $pool->shouldReceive('translate')
        ->once()
        ->withArgs(fn (...$args): bool => ! isset($args[3]['context_hint'])
            && $args[5] === TranslationProviderSelectionStrategy::MachineFirst)
        ->andReturn(new TranslationResult(
            text: '你好',
            source_lang: 'en',
            target_lang: 'zh-CN',
            provider_slug: 'google',
            model: null,
            latency_ms: 1,
            char_count: 5,
        ));
    app()->instance(TranslationProviderPool::class, $pool);

    expect(app(TranslateConversationMessageAction::class)
        ->handleForTargetLang($message, $conversation, 'zh-CN'))->toBeTrue();
});

it('强制重翻随机选择供应商并排除当前译文来源', function () {
    [$message, $conversation] = makeTranslatableVisitorMessage('hello');
    $message->update([
        'payload' => [
            'translations' => [
                'zh-CN' => [
                    'text' => '旧译文',
                    'source_lang' => 'en',
                    'target_lang' => 'zh-CN',
                    'provider_slug' => 'deepseek-current',
                    'latency_ms' => 10,
                ],
            ],
        ],
    ]);

    $pool = Mockery::mock(TranslationProviderPool::class);
    $pool->shouldReceive('translate')
        ->once()
        ->withArgs(fn (...$args): bool => $args[4] === true
            && $args[5] === TranslationProviderSelectionStrategy::Random
            && $args[6] === 'deepseek-current')
        ->andReturn(new TranslationResult(
            text: '新译文',
            source_lang: 'en',
            target_lang: 'zh-CN',
            provider_slug: 'google-next',
            model: null,
            latency_ms: 1,
            char_count: 5,
        ));
    app()->instance(TranslationProviderPool::class, $pool);

    expect(app(TranslateConversationMessageAction::class)
        ->handleForTargetLang($message->refresh(), $conversation, 'zh-CN', force: true))->toBeTrue()
        ->and($message->refresh()->payload['translations']['zh-CN']['provider_slug'])->toBe('google-next');
});
