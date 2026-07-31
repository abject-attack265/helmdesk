<?php

use App\Models\Channel;
use App\Services\Channel\WebChannelEmbedHostGate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 创建带指定 embed 白名单的 Web 渠道。
 *
 * @param  list<string>  $allowedEmbedHosts
 */
function createEmbedGateChannel(array $allowedEmbedHosts): Channel
{
    return Channel::factory()->create([
        'settings' => ['allowed_embed_hosts' => $allowedEmbedHosts],
    ]);
}

test('normalize 把 URL、裸域名、通配符统一为小写 host', function (?string $input, ?string $expected) {
    expect(new WebChannelEmbedHostGate()->normalize($input))->toBe($expected);
})->with([
    '大写 URL 取 host' => ['https://EXAMPLE.com/path?x=1', 'example.com'],
    'IDN 域名归一为 punycode' => ['https://Bücher.example/x', 'xn--bcher-kva.example'],
    '通配 URL 保留通配前缀' => ['https://*.example.com', '*.example.com'],
    '裸域名截掉路径' => ['example.com/path', 'example.com'],
    '通配前缀原样保留' => ['*.foo.com', '*.foo.com'],
    '点前缀原样保留' => ['.foo.com', '.foo.com'],
    '单星号表示不限制' => ['*', '*'],
    '空值返回 null' => [null, null],
    '只有 scheme 无 host' => ['https://', null],
]);

test('isAllowed 按白名单精确与通配匹配', function () {
    $channel = createEmbedGateChannel(['example.com', '.foo.com']);
    $gate = new WebChannelEmbedHostGate;

    expect($gate->isAllowed($channel, 'example.com'))->toBeTrue();
    expect($gate->isAllowed($channel, 'https://example.com'))->toBeTrue();
    expect($gate->isAllowed($channel, 'a.foo.com'))->toBeTrue();
    expect($gate->isAllowed($channel, 'foo.com'))->toBeFalse();
    expect($gate->isAllowed($channel, 'evil.com'))->toBeFalse();
    expect($gate->isAllowed($channel, null))->toBeFalse();
});

test('isAllowed 对 IDN 来源按 punycode 白名单命中', function () {
    $channel = createEmbedGateChannel(['xn--bcher-kva.example']);

    expect(new WebChannelEmbedHostGate()->isAllowed($channel, 'https://bücher.example/page'))->toBeTrue();
});

test('白名单为空或含星号时不限制', function () {
    $gate = new WebChannelEmbedHostGate;

    expect($gate->isAllowed(createEmbedGateChannel([]), 'anything.com'))->toBeTrue();
    expect($gate->isAllowed(createEmbedGateChannel(['*']), 'anything.com'))->toBeTrue();
});

test('record 跳过 HelmDesk 自身 app host、其余 host 落库', function () {
    config(['app.url' => 'https://app.helmdesk.test']);
    $channel = createEmbedGateChannel([]);
    $gate = new WebChannelEmbedHostGate;

    $gate->record($channel, 'app.helmdesk.test');
    expect($channel->refresh()->last_embed_host)->toBeNull();

    $gate->record($channel, 'https://customer.example/pricing');
    $channel->refresh();
    expect($channel->last_embed_host)->toBe('customer.example');
    expect($channel->first_embed_host)->toBe('customer.example');
});
