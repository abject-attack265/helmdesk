<?php

use App\Data\Channel\Telegram\ChannelTelegramSettingsData;
use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Data\Channel\WechatOfficialAccount\ChannelWechatOfficialAccountSettingsData;
use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
});

test('网站渠道 user_token_secret 在 DB 中以密文存储且非敏感配置仍为明文', function () {
    $channel = Channel::factory()->create([
        'settings' => ChannelWebSettingsData::defaults([
            'user_token_secret' => 'plain-secret-value-1234567890',
            'allowed_embed_hosts' => ['example.com'],
        ]),
    ]);

    $raw = DB::table('channels')->where('id', $channel->id)->value('settings');
    $decoded = json_decode($raw, true);

    // 密钥字段在落库 JSON 中已是密文，明文不出现。
    expect($decoded['user_token_secret'])->not->toBe('plain-secret-value-1234567890')
        ->and(Crypt::decryptString($decoded['user_token_secret']))->toBe('plain-secret-value-1234567890')
        // 非敏感配置仍为明文，便于运维查看。
        ->and($decoded['allowed_embed_hosts'])->toBe(['example.com'])
        // 通过 cast 读出时密钥自动解密还原。
        ->and($channel->fresh()->settings->user_token_secret)->toBe('plain-secret-value-1234567890');
});

test('Telegram 渠道凭证在 DB 中以密文存储', function () {
    $botToken = '777000111:AAEncrypted_settings_token_abcdefghijk';
    $channel = Channel::factory()->create([
        'type' => ChannelType::Telegram,
        'settings' => ChannelTelegramSettingsData::from([
            'bot_token' => $botToken,
            'webhook_secret' => 'tg-webhook-secret-abcdef',
        ]),
    ]);

    $raw = DB::table('channels')->where('id', $channel->id)->value('settings');
    $decoded = json_decode($raw, true);

    expect($decoded['bot_token'])->not->toBe($botToken)
        ->and(Crypt::decryptString($decoded['bot_token']))->toBe($botToken)
        ->and($decoded['webhook_secret'])->not->toBe('tg-webhook-secret-abcdef')
        ->and(Crypt::decryptString($decoded['webhook_secret']))->toBe('tg-webhook-secret-abcdef')
        ->and($channel->fresh()->settings->bot_token)->toBe($botToken)
        ->and($channel->fresh()->settings->webhook_secret)->toBe('tg-webhook-secret-abcdef');
});

test('微信公众号凭证在 DB 中以密文存储', function () {
    $channel = Channel::factory()->create([
        'type' => ChannelType::WechatOfficialAccount,
        'settings' => ChannelWechatOfficialAccountSettingsData::from([
            'app_id' => 'wx1234567890abcdef',
            'app_secret' => 'wechat-app-secret',
            'token' => 'wechat-token',
            'aes_key' => str_repeat('a', 43),
        ]),
    ]);

    $decoded = json_decode(DB::table('channels')->where('id', $channel->id)->value('settings'), true);

    expect($decoded['app_id'])->toBe('wx1234567890abcdef')
        ->and(Crypt::decryptString($decoded['app_secret']))->toBe('wechat-app-secret')
        ->and(Crypt::decryptString($decoded['token']))->toBe('wechat-token')
        ->and(Crypt::decryptString($decoded['aes_key']))->toBe(str_repeat('a', 43));
});
