<?php

use App\Actions\Reception\CaptureTelegramConversationContextAction;
use App\Data\Conversation\ChannelContext\TelegramConversationChannelContextData;
use App\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('写入 Telegram 渠道上下文用户元数据', function () {
    $conversation = Conversation::factory()->create();

    app(CaptureTelegramConversationContextAction::class)->handle($conversation, [
        'tg_user_id' => '123456789',
        'username' => 'mia_support',
        'language_code' => 'en',
        'is_premium' => true,
        'is_bot' => false,
        'chat_type' => 'private',
    ]);

    $context = $conversation->fresh()->channel_context;

    expect($context)->toBeInstanceOf(TelegramConversationChannelContextData::class)
        ->and($context->channel_type)->toBe('telegram')
        ->and($context->tg_user_id)->toBe('123456789')
        ->and($context->username)->toBe('mia_support')
        ->and($context->language_code)->toBe('en')
        ->and($context->is_premium)->toBeTrue()
        ->and($context->is_bot)->toBeFalse()
        ->and($context->chat_type)->toBe('private');
});

test('后续消息缺省字段时保留先前 Telegram 快照', function () {
    $conversation = Conversation::factory()->create();

    app(CaptureTelegramConversationContextAction::class)->handle($conversation, [
        'tg_user_id' => '123456789',
        'username' => 'mia_support',
        'language_code' => 'en',
        'is_premium' => true,
        'chat_type' => 'private',
    ]);

    // 模拟仅带 tg_user_id、富字段缺省的入站。
    app(CaptureTelegramConversationContextAction::class)->handle($conversation->fresh(), [
        'tg_user_id' => '123456789',
    ]);

    $context = $conversation->fresh()->channel_context;

    expect($context->language_code)->toBe('en')
        ->and($context->is_premium)->toBeTrue()
        ->and($context->username)->toBe('mia_support')
        ->and($context->chat_type)->toBe('private');
});
