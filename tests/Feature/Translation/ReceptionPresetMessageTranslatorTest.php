<?php

use App\Actions\Translation\TranslateConversationMessageAction;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Reception\ReceptionPresetMessageTranslator;
use App\Services\Translation\TranslationProviderPool;
use App\Services\Translation\TranslationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('没有可用翻译供应商时原样返回访客侧预设文案', function () {
    $action = Mockery::mock(TranslateConversationMessageAction::class);
    $action->shouldNotReceive('translateContentForTargetLang');
    $pool = Mockery::mock(TranslationProviderPool::class);
    $pool->shouldReceive('hasUsable')->once()->andReturnFalse();

    $conversation = Conversation::factory()->create(['visitor_locale' => 'en']);
    $result = new ReceptionPresetMessageTranslator($action, $pool)
        ->translateForVisitor($conversation, '您好');

    expect($result)->toBe([
        'available' => true,
        'content' => '您好',
        'content_locale' => null,
        'payload' => null,
    ]);
});

it('预设文案翻译后向访客发送译文并为客服保留原文', function () {
    $action = Mockery::mock(TranslateConversationMessageAction::class);
    $action->shouldReceive('translateContentForTargetLang')
        ->once()
        ->with('您好', 'en')
        ->andReturn(new TranslationResult(
            text: 'Hello',
            source_lang: 'zh-CN',
            target_lang: 'en',
            provider_slug: 'deepseek-test',
            model: 'deepseek-v4-flash',
            latency_ms: 1,
            char_count: 2,
        ));
    $pool = Mockery::mock(TranslationProviderPool::class);
    $pool->shouldReceive('hasUsable')->once()->andReturnTrue();

    $actor = User::factory()->create(['locale' => 'zh-CN']);
    $conversation = Conversation::factory()->create(['visitor_locale' => 'en']);
    $result = new ReceptionPresetMessageTranslator($action, $pool)
        ->translateForVisitor($conversation, '您好', $actor);

    expect($result['content'])->toBe('Hello')
        ->and($result['content_locale'])->toBe('en')
        ->and($result['payload']['zh-CN']['text'])->toBe('您好')
        ->and($result['payload']['zh-CN']['provider_slug'])->toBe('source');
});
