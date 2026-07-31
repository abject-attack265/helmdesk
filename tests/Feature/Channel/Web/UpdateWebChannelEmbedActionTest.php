<?php

use App\Actions\Channel\Web\UpdateWebChannelEmbedAction;
use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Data\Channel\Web\FormUpdateWebChannelEmbedData;
use App\Data\Channel\Web\WebChannelQueryParamMappingData;
use App\Enums\WebChannelParamTarget;
use App\Enums\WebChannelParamTrust;
use App\Enums\WebChannelParamWriteMode;
use App\Models\Channel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\DataCollection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->instance = createSystemSettings();
    $this->channel = Channel::factory()->create([
        'settings' => ChannelWebSettingsData::defaults([
            'user_token_secret' => 'existing-secret-1234567890abcdef',
        ]),
    ]);
});

test('保存业务参数映射时保持签名密钥不变', function () {
    UpdateWebChannelEmbedAction::run($this->channel, FormUpdateWebChannelEmbedData::from());

    $settings = $this->channel->fresh()->settings;
    expect($settings->user_token_secret)->toBe('existing-secret-1234567890abcdef')
        ->and($settings->allowed_embed_hosts)->toBeNull()
        ->and($settings->query_param_mappings)->toBe([]);
});

test('query_param_mappings 一次提交即覆盖原值且按 param/target/key 去重', function () {
    $mappings = WebChannelQueryParamMappingData::collect([
        [
            'param_name' => 'utm_source',
            'target' => WebChannelParamTarget::Tag->value,
            'target_key' => '  src:{value}  ',
            'trust' => WebChannelParamTrust::Always->value,
            'write_mode' => WebChannelParamWriteMode::Overwrite->value,
        ],
        [
            'param_name' => 'utm_source',
            'target' => WebChannelParamTarget::Tag->value,
            'target_key' => 'src:{value}',
            'trust' => WebChannelParamTrust::SignedOnly->value,
            'write_mode' => WebChannelParamWriteMode::OnlyIfEmpty->value,
        ],
        [
            'param_name' => 'plan',
            'target' => WebChannelParamTarget::Attribute->value,
            'target_key' => 'plan_level',
            'trust' => WebChannelParamTrust::Always->value,
            'write_mode' => WebChannelParamWriteMode::OnlyIfEmpty->value,
        ],
    ], DataCollection::class);

    UpdateWebChannelEmbedAction::run($this->channel, new FormUpdateWebChannelEmbedData(
        query_param_mappings: $mappings,
    ));

    $stored = $this->channel->fresh()->settings->query_param_mappings;
    $first = $stored[0];
    $second = $stored[1];
    expect($stored)->toHaveCount(2)
        ->and($first->param_name)->toBe('utm_source')
        ->and($first->target_key)->toBe('src:{value}')
        ->and($first->trust)->toBe(WebChannelParamTrust::SignedOnly)
        ->and($second->param_name)->toBe('plan');
});

test('保存映射时整体替换映射且不影响嵌入域名白名单', function () {
    $this->channel->update([
        'settings' => ChannelWebSettingsData::defaults([
            'allowed_embed_hosts' => ['old-a.example.com', 'old-b.example.com'],
            'query_param_mappings' => [
                [
                    'param_name' => 'old_a',
                    'target' => WebChannelParamTarget::Tag->value,
                    'target_key' => 'old-a:{value}',
                    'trust' => WebChannelParamTrust::Always->value,
                    'write_mode' => WebChannelParamWriteMode::Overwrite->value,
                ],
                [
                    'param_name' => 'old_b',
                    'target' => WebChannelParamTarget::Tag->value,
                    'target_key' => 'old-b:{value}',
                    'trust' => WebChannelParamTrust::Always->value,
                    'write_mode' => WebChannelParamWriteMode::Overwrite->value,
                ],
            ],
        ]),
    ]);

    $mappings = WebChannelQueryParamMappingData::collect([
        [
            'param_name' => 'new',
            'target' => WebChannelParamTarget::Tag->value,
            'target_key' => 'new:{value}',
            'trust' => WebChannelParamTrust::Always->value,
            'write_mode' => WebChannelParamWriteMode::Overwrite->value,
        ],
    ], DataCollection::class);

    UpdateWebChannelEmbedAction::run($this->channel, new FormUpdateWebChannelEmbedData(
        query_param_mappings: $mappings,
    ));

    $settings = $this->channel->fresh()->settings;
    $storedMapping = $settings->query_param_mappings[0];

    expect($settings->query_param_mappings)->toHaveCount(1)
        ->and($storedMapping->param_name)->toBe('new')
        // 白名单不属于映射表单，保存映射后保持原值。
        ->and($settings->allowed_embed_hosts)->toBe(['old-a.example.com', 'old-b.example.com']);
});

test('Attribute / Tag 目标缺少 target_key 时校验失败', function () {
    $mappings = WebChannelQueryParamMappingData::collect([
        [
            'param_name' => 'plan',
            'target' => WebChannelParamTarget::Attribute->value,
            'target_key' => null,
            'trust' => WebChannelParamTrust::Always->value,
            'write_mode' => WebChannelParamWriteMode::OnlyIfEmpty->value,
        ],
    ], DataCollection::class);

    expect(fn () => UpdateWebChannelEmbedAction::run($this->channel, new FormUpdateWebChannelEmbedData(
        query_param_mappings: $mappings,
    )))->toThrow(ValidationException::class);
});
