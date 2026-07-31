<?php

use App\Actions\Channel\Web\RegenerateWebChannelUserTokenSecretAction;
use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
});

test('重新生成签名访客密钥会立即覆盖当前密钥并返回明文', function () {
    $channel = Channel::factory()->create([
        'settings' => ChannelWebSettingsData::defaults([
            'user_token_secret' => 'current-secret-1234567890',
        ]),
    ]);

    RegenerateWebChannelUserTokenSecretAction::run($channel);
    $storedSecret = $channel->fresh()->settings->user_token_secret;

    expect($storedSecret)
        ->not->toBe('current-secret-1234567890')
        ->and(strlen((string) $storedSecret))->toBe(64);
});

test('重置密钥路由会回到详情页并更新密钥', function () {
    $channel = Channel::factory()->create([
        'settings' => ChannelWebSettingsData::defaults([
            'user_token_secret' => 'current-secret-1234567890',
        ]),
    ]);

    $response = $this->actingAs($this->user)
        ->from(route('app.manage.channels.web.show', [
            'channel' => $channel->id,
        ]))
        ->post(route('app.manage.channels.web.user-token-secret.regenerate', [
            'channel' => $channel->id,
        ]));

    $storedSecret = $channel->fresh()->settings->user_token_secret;

    $response
        ->assertRedirect(route('app.manage.channels.web.show', [
            'channel' => $channel->id,
        ]));

    expect($storedSecret)->not->toBe('current-secret-1234567890');
});
