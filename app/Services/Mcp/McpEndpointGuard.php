<?php

namespace App\Services\Mcp;

use RuntimeException;
use Uri\WhatWg\Url;

/**
 * 外联 MCP 端点的 SSRF 防护。
 *
 * MCP 端点由应用 owner 自行填写,握手/同步/AI 运行时都会让宿主主动向该地址发请求并附带认证 header,
 * 若指向环回 / 内网 / 链路本地(如云元数据 169.254.169.254)即可探测宿主内网或外带响应。
 * 保存时(Form*Data 校验)与真正发起请求前(运行时兜底,防 DNS 重绑定)都应过这里。
 */
class McpEndpointGuard
{
    /**
     * 校验 endpoint 是否可安全外联;指向私有/保留地址时抛出 RuntimeException。
     */
    public static function assertSafe(string $url): void
    {
        // WHATWG 解析会把 IDN / 全角混淆字符归一成 ASCII host(如 ①②⑦.⓪.⓪.① → 127.0.0.1),
        // 与 HTTP 客户端实际连接的目标一致,避免 parse_url 返回原始字节导致的绕过。
        $host = Url::parse($url)?->getAsciiHost();
        if ($host === null || $host === '') {
            throw new RuntimeException(__('integration.runtime.validate.unsafe_endpoint'));
        }

        // 去掉 IPv6 字面量的方括号(如 [::1])
        $host = trim($host, '[]');

        $ips = self::resolveIps($host);
        if ($ips === []) {
            return;
        }

        foreach ($ips as $ip) {
            if (self::isBlockedIp($ip)) {
                throw new RuntimeException(__('integration.runtime.validate.unsafe_endpoint'));
            }
        }
    }

    /**
     * 布尔包装,供表单校验规则调用。
     */
    #[\NoDiscard('忽略 SSRF 校验结果等于没有校验')]
    public static function isSafe(string $url): bool
    {
        try {
            self::assertSafe($url);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    /**
     * 把 host 解析成 IP 列表:字面量 IP 直接返回,本地主机名判为环回,其余做 DNS 解析。
     *
     * @return list<string>
     */
    private static function resolveIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        if (in_array(strtolower($host), ['localhost', 'localhost.localdomain'], true)) {
            return ['127.0.0.1'];
        }

        $ips = [];
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                foreach (['ip', 'ipv6'] as $key) {
                    if (isset($record[$key]) && is_string($record[$key])) {
                        $ips[] = $record[$key];
                    }
                }
            }
        }

        return $ips;
    }

    /**
     * 判断 IP 是否落在禁止外联的私有/保留/环回/链路本地范围。
     */
    private static function isBlockedIp(string $ip): bool
    {
        // 公网范围(剔除私有 + 保留)之外一律拦截;含 127/8、169.254/16、10/8、172.16/12、192.168/16、::1 等
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
