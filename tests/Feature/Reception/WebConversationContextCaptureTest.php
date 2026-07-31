<?php

use App\Actions\Reception\CaptureWebConversationContextAction;
use App\Data\Conversation\ChannelContext\WebConversationChannelContextData;
use App\Models\Conversation;
use App\Services\Reception\UserAgentParser;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const CHROME_MAC_UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
const IPHONE_UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

test('建会话时落入 Web 渠道上下文快照并记录首条浏览轨迹', function () {
    $conversation = Conversation::factory()->create();

    app(CaptureWebConversationContextAction::class)->handle(
        $conversation,
        [
            'current_url' => 'https://shop.example.com/pricing',
            'landing_url' => 'https://shop.example.com/',
            'referrer' => 'https://google.com/',
            'user_agent' => 'Mozilla/5.0 (Macintosh)',
            'ip_address' => '203.0.113.7',
            'browser_language' => 'en-US',
        ],
        ['utm_source' => 'google', 'ref' => 'newsletter'],
        true,
    );

    $context = $conversation->fresh()->channel_context;

    expect($context)->toBeInstanceOf(WebConversationChannelContextData::class)
        ->and($context->channel_type)->toBe('web')
        ->and($context->current_url)->toBe('https://shop.example.com/pricing')
        ->and($context->entry_url)->toBe('https://shop.example.com/pricing')
        ->and($context->landing_url)->toBe('https://shop.example.com/')
        ->and($context->referrer)->toBe('https://google.com/')
        ->and($context->user_agent)->toBe('Mozilla/5.0 (Macintosh)')
        ->and($context->ip_address)->toBe('203.0.113.7')
        ->and($context->browser_language)->toBe('en-US')
        ->and($context->utm_source)->toBe('google')
        ->and($context->ref)->toBe('newsletter')
        ->and($context->captured_at)->not->toBeNull();

    expect($conversation->pageViews()->get())->toHaveCount(1)
        ->and($conversation->pageViews()->first()->url)->toBe('https://shop.example.com/pricing');
});

test('恢复会话保留入站快照仅刷新当前页并追加轨迹', function () {
    $conversation = Conversation::factory()->create();

    app(CaptureWebConversationContextAction::class)->handle(
        $conversation,
        [
            'current_url' => 'https://shop.example.com/pricing',
            'landing_url' => 'https://shop.example.com/',
            'referrer' => 'https://google.com/',
        ],
        [],
        true,
    );

    app(CaptureWebConversationContextAction::class)->handle(
        $conversation->fresh(),
        ['current_url' => 'https://shop.example.com/checkout'],
        [],
        false,
    );

    $context = $conversation->fresh()->channel_context;

    expect($context->current_url)->toBe('https://shop.example.com/checkout')
        ->and($context->landing_url)->toBe('https://shop.example.com/')
        ->and($context->referrer)->toBe('https://google.com/');

    $views = $conversation->pageViews()->get();
    expect($views)->toHaveCount(2)
        ->and($views->first()->url)->toBe('https://shop.example.com/pricing')
        ->and($views->last()->url)->toBe('https://shop.example.com/checkout');
});

test('当前页与最近一条轨迹相同时不重复记录', function () {
    $conversation = Conversation::factory()->create();

    $client = ['current_url' => 'https://shop.example.com/pricing'];

    app(CaptureWebConversationContextAction::class)->handle($conversation, $client, [], true);
    app(CaptureWebConversationContextAction::class)->handle($conversation->fresh(), $client, [], false);

    expect($conversation->pageViews()->count())->toBe(1);
});

test('回访曾访问过的旧页面仍记录轨迹（仅与最近一条去重）', function () {
    $conversation = Conversation::factory()->create();

    $visit = fn (string $url, bool $created) => app(CaptureWebConversationContextAction::class)->handle(
        $conversation->fresh(),
        ['current_url' => $url],
        [],
        $created,
    );

    // A → B → A：第三次回到 A 与最近一条（B）不同，应记录，不能被「最早一条」误判为重复。
    $visit('https://shop.example.com/a', true);
    $visit('https://shop.example.com/b', false);
    $visit('https://shop.example.com/a', false);

    $urls = $conversation->pageViews()->get()->pluck('url')->all();

    expect($urls)->toBe([
        'https://shop.example.com/a',
        'https://shop.example.com/b',
        'https://shop.example.com/a',
    ]);
});

test('恢复会话时旧快照缺 UA 用本次 UA 补齐派生字段', function () {
    $conversation = Conversation::factory()->create();

    // 首次建会话无 UA：browser/platform/device 均为空。
    app(CaptureWebConversationContextAction::class)->handle(
        $conversation,
        ['current_url' => 'https://shop.example.com/pricing'],
        [],
        true,
    );

    $first = $conversation->fresh()->channel_context;
    expect($first->user_agent)->toBeNull()
        ->and($first->browser)->toBeNull();

    // 恢复时带上真实 UA，应补齐派生字段。
    app(CaptureWebConversationContextAction::class)->handle(
        $conversation->fresh(),
        [
            'current_url' => 'https://shop.example.com/checkout',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ],
        [],
        false,
    );

    $context = $conversation->fresh()->channel_context;

    expect($context->browser)->toBe('Chrome')
        ->and($context->platform)->toBe('OS X')
        ->and($context->device_type)->toBe('desktop');
});

test('无当前页时不追加轨迹', function () {
    $conversation = Conversation::factory()->create();

    app(CaptureWebConversationContextAction::class)->handle(
        $conversation,
        ['user_agent' => 'Mozilla/5.0'],
        [],
        true,
    );

    $context = $conversation->fresh()->channel_context;

    expect($context)->toBeInstanceOf(WebConversationChannelContextData::class)
        ->and($context->user_agent)->toBe('Mozilla/5.0')
        ->and($context->current_url)->toBeNull()
        ->and($conversation->pageViews()->count())->toBe(0);
});

test('建会话时由 UA 派生浏览器与设备类型落入渠道上下文', function () {
    $conversation = Conversation::factory()->create();

    app(CaptureWebConversationContextAction::class)->handle(
        $conversation,
        [
            'current_url' => 'https://shop.example.com/pricing',
            'user_agent' => CHROME_MAC_UA,
            'ip_address' => '8.8.8.8',
        ],
        [],
        true,
    );

    $context = $conversation->fresh()->channel_context;

    expect($context->browser)->toBe('Chrome')
        ->and($context->platform)->toBe('OS X')
        ->and($context->device_type)->toBe('desktop')
        ->and($context->user_agent)->toBe(CHROME_MAC_UA);
});

describe('UA 解析', function () {
    test('UA 解析桌面 Chrome 浏览器与平台', function () {
        $parsed = app(UserAgentParser::class)->parse(CHROME_MAC_UA);

        expect($parsed['browser'])->toBe('Chrome')
            ->and($parsed['browser_version'])->toStartWith('120')
            ->and($parsed['platform'])->toBe('OS X')
            ->and($parsed['device_type'])->toBe('desktop');
    });

    test('UA 解析移动端 iPhone 设备类型', function () {
        $parsed = app(UserAgentParser::class)->parse(IPHONE_UA);

        expect($parsed['device_type'])->toBe('mobile')
            ->and($parsed['platform'])->toBe('iOS');
    });
});
