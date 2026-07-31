<?php

use App\Services\Mcp\McpEndpointGuard;

test('拦截指向环回、内网、链路本地地址的 endpoint', function (string $url) {
    expect(McpEndpointGuard::isSafe($url))->toBeFalse();
    expect(fn () => McpEndpointGuard::assertSafe($url))->toThrow(RuntimeException::class);
})->with([
    'IPv4 环回' => ['http://127.0.0.1/mcp'],
    'IPv6 环回' => ['http://[::1]/mcp'],
    '10 段内网' => ['https://10.0.0.8/mcp'],
    '172 段内网' => ['https://172.16.0.1/mcp'],
    '192 段内网' => ['https://192.168.1.1/mcp'],
    '云元数据链路本地' => ['http://169.254.169.254/latest/meta-data'],
    'localhost 主机名' => ['http://localhost:8080/mcp'],
]);

test('拦截 IDN 混淆字符伪装的环回地址', function () {
    // WHATWG 解析将全角带圈数字归一成 127.0.0.1。
    expect(McpEndpointGuard::isSafe('http://①②⑦.⓪.⓪.①/mcp'))->toBeFalse();
});

test('无法解析出 host 的 endpoint 一律拒绝', function (string $url) {
    expect(McpEndpointGuard::isSafe($url))->toBeFalse();
})->with([
    '非 URL 字符串' => ['not-a-url'],
    '相对路径' => ['/internal/api'],
    '空 host' => ['https://'],
]);

test('公网字面量 IP 的 endpoint 放行', function () {
    expect(McpEndpointGuard::isSafe('https://93.184.216.34/mcp'))->toBeTrue();
});
