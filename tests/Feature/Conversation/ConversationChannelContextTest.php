<?php

use App\Data\Conversation\ChannelContext\TelegramConversationChannelContextData;
use App\Data\Conversation\ChannelContext\WebConversationChannelContextData;
use App\Data\Conversation\ConversationSummaryData;
use App\Models\Conversation;
use App\Models\ConversationPageView;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('Web 渠道上下文按 channel_type 往返为 Web 变体', function () {
    $conversation = Conversation::factory()->create();

    $conversation->channel_context = new WebConversationChannelContextData(
        current_url: 'https://shop.example.com/pricing',
        referrer: 'https://google.com/',
        user_agent: 'Mozilla/5.0',
        ip_address: '203.0.113.7',
        geo_country: 'JP',
    );
    $conversation->save();

    $reloaded = $conversation->fresh()->channel_context;

    expect($reloaded)->toBeInstanceOf(WebConversationChannelContextData::class)
        ->and($reloaded->channel_type)->toBe('web')
        ->and($reloaded->current_url)->toBe('https://shop.example.com/pricing')
        ->and($reloaded->referrer)->toBe('https://google.com/')
        ->and($reloaded->ip_address)->toBe('203.0.113.7')
        ->and($reloaded->geo_country)->toBe('JP');
});

test('Telegram 渠道上下文按 channel_type 往返为 Telegram 变体', function () {
    $conversation = Conversation::factory()->create();

    $conversation->channel_context = new TelegramConversationChannelContextData(
        tg_user_id: '123456789',
        username: 'mia_support',
        language_code: 'en',
        is_premium: true,
        chat_type: 'private',
    );
    $conversation->save();

    $reloaded = $conversation->fresh()->channel_context;

    expect($reloaded)->toBeInstanceOf(TelegramConversationChannelContextData::class)
        ->and($reloaded->channel_type)->toBe('telegram')
        ->and($reloaded->username)->toBe('mia_support')
        ->and($reloaded->language_code)->toBe('en')
        ->and($reloaded->is_premium)->toBeTrue()
        ->and($reloaded->chat_type)->toBe('private');
});

test('未采集时 channel_context 为 null', function () {
    $conversation = Conversation::factory()->create();

    expect($conversation->fresh()->channel_context)->toBeNull();
});

test('会话摘要 Data 把 channel_context 透传给前端', function () {
    $conversation = Conversation::factory()->create();
    $conversation->channel_context = new WebConversationChannelContextData(
        current_url: 'https://shop.example.com/pricing',
    );
    $conversation->save();

    $data = ConversationSummaryData::fromModel($conversation->fresh());

    expect($data->channel_context)->toBeInstanceOf(WebConversationChannelContextData::class)
        ->and($data->channel_context->current_url)->toBe('https://shop.example.com/pricing');
});

test('会话浏览轨迹按访问时间挂在会话上', function () {
    $conversation = Conversation::factory()->create();

    ConversationPageView::factory()->forConversation($conversation)->create([
        'url' => 'https://shop.example.com/pricing',
        'viewed_at' => now()->subMinute(),
    ]);
    ConversationPageView::factory()->forConversation($conversation)->create([
        'url' => 'https://shop.example.com/checkout',
        'viewed_at' => now(),
    ]);

    $views = $conversation->pageViews()->get();

    expect($views)->toHaveCount(2)
        ->and($views->first()->url)->toBe('https://shop.example.com/pricing')
        ->and($views->last()->url)->toBe('https://shop.example.com/checkout');
});
