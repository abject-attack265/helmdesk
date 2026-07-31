<?php

use App\Services\Reception\VisitorRequestContext;
use Illuminate\Http\Request;

/**
 * 构造带指定 header / cookie 的请求。
 *
 * @param  array<string, string>  $headers
 * @param  array<string, string>  $cookies
 */
function visitorRequest(array $headers = [], array $cookies = [], string $ua = 'TestUA', string $ip = '203.0.113.7'): Request
{
    $server = ['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $ua];
    foreach ($headers as $key => $value) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $key))] = $value;
    }

    return Request::create('/api/chat/abc/messages', 'POST', [], $cookies, [], $server);
}

$validToken = str_repeat('a', 32);

test('会话 token 优先取 header，其次 cookie', function () use ($validToken) {
    $other = str_repeat('b', 32);

    $fromHeader = VisitorRequestContext::fromRequest(
        visitorRequest(['X-Helmdesk-Visitor-Token' => $validToken], ['helmdesk_visitor_abc' => $other]),
        'abc',
    );
    expect($fromHeader->sessionToken)->toBe($validToken);

    $fromCookie = VisitorRequestContext::fromRequest(
        visitorRequest([], ['helmdesk_visitor_abc' => $other]),
        'abc',
    );
    expect($fromCookie->sessionToken)->toBe($other);
});

test('非法会话 token 归一为 null', function () {
    $ctx = VisitorRequestContext::fromRequest(
        visitorRequest(['X-Helmdesk-Visitor-Token' => '太短'], []),
        'abc',
    );

    expect($ctx->sessionToken)->toBeNull();
});

test('从 Authorization Bearer 读取 userToken', function () {
    $ctx = VisitorRequestContext::fromRequest(
        visitorRequest(['Authorization' => 'Bearer sk-visitor-123']),
        'abc',
    );

    expect($ctx->userToken)->toBe('sk-visitor-123');
});

test('入口形态识别 widget / standalone', function () {
    expect(VisitorRequestContext::fromRequest(visitorRequest(['X-Helmdesk-Entry-Mode' => 'widget']), 'abc')->entryMode)
        ->toBe(VisitorRequestContext::ENTRY_WIDGET);
    expect(VisitorRequestContext::fromRequest(visitorRequest([]), 'abc')->entryMode)
        ->toBe(VisitorRequestContext::ENTRY_STANDALONE);
});

test('收集访客环境与客户端信号', function () {
    $ctx = VisitorRequestContext::fromRequest(visitorRequest([
        'X-Helmdesk-Visitor-Locale' => 'zh-CN',
        'X-Helmdesk-Visitor-Timezone' => 'Asia/Shanghai',
        'CF-IPCountry' => 'CN',
        'X-Helmdesk-Visitor-Url' => 'https://shop.test/cart',
        'Referer' => 'https://google.com',
    ]), 'abc');

    expect($ctx->visitorEnvironment)->toMatchArray([
        'locale' => 'zh-CN',
        'timezone' => 'Asia/Shanghai',
        'country' => 'CN',
    ]);
    expect($ctx->visitorClient)->toMatchArray([
        'user_agent' => 'TestUA',
        'ip_address' => '203.0.113.7',
        'browser_language' => 'zh-CN',
        'current_url' => 'https://shop.test/cart',
        'referrer' => 'https://google.com',
    ]);
});

test('locale 回退到 Accept-Language 首个标签', function () {
    $ctx = VisitorRequestContext::fromRequest(
        visitorRequest(['Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8']),
        'abc',
    );

    expect($ctx->visitorEnvironment['locale'])->toBe('fr-FR');
});

test('locale 保留 Accept-Language 扩展标签', function () {
    $ctx = VisitorRequestContext::fromRequest(
        visitorRequest(['Accept-Language' => 'en-US-u-ca-gregory,en;q=0.8']),
        'abc',
    );

    expect($ctx->visitorEnvironment['locale'])->toBe('en-US-u-ca-gregory');
});

test('query 参数过滤黑名单与非法 key', function () {
    $ctx = VisitorRequestContext::fromRequest(visitorRequest([
        'X-Helmdesk-Query-Params' => json_encode([
            'order_id' => '12345',
            'user_token' => '应被过滤',
            'bad key!' => '应被过滤',
            'utm.source' => 'google',
        ]),
    ]), 'abc');

    expect($ctx->queryParams)->toBe([
        'order_id' => '12345',
        'utm.source' => 'google',
    ]);
});

test('会话 token 变化时生成 cookie，widget+https 用 SameSite=None', function () use ($validToken) {
    $request = visitorRequest(['X-Helmdesk-Entry-Mode' => 'widget', 'X-Forwarded-Proto' => 'https']);
    $ctx = VisitorRequestContext::fromRequest($request, 'abc');

    $cookie = $ctx->sessionCookie($request, $validToken);

    expect($cookie)->not->toBeNull()
        ->and($cookie->getName())->toBe('helmdesk_visitor_abc')
        ->and($cookie->getValue())->toBe($validToken)
        ->and($cookie->getSameSite())->toBe('none')
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->isSecure())->toBeTrue();
});

test('会话 token 未变化时不生成 cookie', function () use ($validToken) {
    $request = visitorRequest(['X-Helmdesk-Visitor-Token' => $validToken]);
    $ctx = VisitorRequestContext::fromRequest($request, 'abc');

    expect($ctx->sessionCookie($request, $validToken))->toBeNull();
});

test('standalone 入口用 SameSite=Lax', function () use ($validToken) {
    $request = visitorRequest([]);
    $ctx = VisitorRequestContext::fromRequest($request, 'abc');

    $cookie = $ctx->sessionCookie($request, $validToken);

    expect($cookie->getSameSite())->toBe('lax');
});
