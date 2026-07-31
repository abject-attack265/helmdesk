<?php

use App\Actions\Channel\Web\UpdateWebChannelAccessAction;
use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Data\Channel\Web\FormUpdateWebChannelAccessData;
use App\Enums\WebChannelWidgetEntryPosition;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->instance = createSystemSettings();
    $this->channel = Channel::factory()->create([
        'settings' => ChannelWebSettingsData::defaults([
            'user_token_secret' => 'existing-secret-1234567890abcdef',
        ]),
    ]);
});

test('白名单去重并规范化主机', function () {
    UpdateWebChannelAccessAction::run($this->channel, FormUpdateWebChannelAccessData::from([
        'allowed_embed_hosts' => ['  Example.com', 'example.com', 'https://foo.bar/path', ' '],
    ]));

    $hosts = $this->channel->fresh()->settings->allowed_embed_hosts;
    expect($hosts)->toEqualCanonicalizing(['example.com', 'foo.bar']);
});

test('白名单全空时回退为 null（表示不限制）', function () {
    UpdateWebChannelAccessAction::run($this->channel, FormUpdateWebChannelAccessData::from([
        'allowed_embed_hosts' => ['', '  '],
    ]));

    expect($this->channel->fresh()->settings->allowed_embed_hosts)->toBeNull();
});

test('聊天链接附加 query 会写入设置并去掉首部问号', function () {
    UpdateWebChannelAccessAction::run($this->channel, FormUpdateWebChannelAccessData::from([
        'allowed_embed_hosts' => ['example.com'],
        'standalone_link_query' => '  ?utm_source=homepage&campaign=spring ',
    ]));

    expect($this->channel->fresh()->settings->standalone_link_query)
        ->toBe('utm_source=homepage&campaign=spring');
});

test('聊天链接附加 query 为空串时回落为 null', function () {
    $this->channel->update([
        'settings' => ChannelWebSettingsData::defaults([
            'standalone_link_query' => 'utm_source=existing',
        ]),
    ]);

    UpdateWebChannelAccessAction::run($this->channel, FormUpdateWebChannelAccessData::from([
        'allowed_embed_hosts' => ['example.com'],
        'standalone_link_query' => '   ',
    ]));

    expect($this->channel->fresh()->settings->standalone_link_query)->toBeNull();
});

test('仅更新白名单时其余设置保持不变', function () {
    $this->channel->update([
        'settings' => ChannelWebSettingsData::defaults([
            'widget' => [
                'entry' => [
                    'position' => WebChannelWidgetEntryPosition::Left->value,
                    'bottom_offset' => 42,
                ],
                'unread_badge_enabled' => true,
            ],
            'user_token_secret' => 'existing-secret-1234567890abcdef',
        ]),
    ]);

    UpdateWebChannelAccessAction::run($this->channel, FormUpdateWebChannelAccessData::from([
        'allowed_embed_hosts' => ['shop.example.com'],
    ]));

    $settings = $this->channel->fresh()->settings;
    expect($settings->allowed_embed_hosts)->toBe(['shop.example.com'])
        ->and($settings->widget->entry?->position)->toBe(WebChannelWidgetEntryPosition::Left)
        ->and($settings->widget->entry?->bottom_offset)->toBe(42)
        ->and($settings->widget->unread_badge_enabled)->toBeTrue()
        ->and($settings->user_token_secret)->toBe('existing-secret-1234567890abcdef');
});
