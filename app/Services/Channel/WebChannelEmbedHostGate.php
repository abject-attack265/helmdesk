<?php

namespace App\Services\Channel;

use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Models\Channel;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Uri\WhatWg\Url;

/**
 * 渠道嵌入主机校验/落库门面。
 *
 * - normalize：把 Origin / Referer 解析出的 host 统一为小写、去掉非法字符。
 * - guard：根据渠道 allowed_embed_hosts 决定是否放行；空白名单或 * 视为不限制。
 * - record：把命中的 embed host 写入 channels 表的 first/last embed 字段，便于装机健康度观察。
 */
class WebChannelEmbedHostGate
{
    /**
     * 把候选 host 字符串清洗为可比较/落库的小写主机名。
     *
     * 允许传入 URL（http(s)://foo.bar/path）；会被截断为 host 部分。
     * 同时允许通配前缀 `*.foo.bar` 和 `.foo.bar`，便于管理员配置白名单。
     */
    public function normalize(?string $host): ?string
    {
        $value = strtolower(trim((string) $host));
        if ($value === '') {
            return null;
        }

        if ($value === '*') {
            return '*';
        }

        if (str_contains($value, '://')) {
            // WHATWG（浏览器同款）解析，IDN 域名归一为小写 ASCII host，与 Origin 头的取值口径一致。
            $parsed = Url::parse($value)?->getAsciiHost();
            if ($parsed === null || $parsed === '') {
                return null;
            }
            $value = $parsed;
        } else {
            $value = strtok($value, '/');
            if (! is_string($value) || $value === '') {
                return null;
            }
        }

        if (strlen($value) > 253) {
            return null;
        }

        if (! preg_match('/^\*?\.?[a-z0-9.\-:]+$/', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * 校验 embed host 是否被允许；不允许时抛 403。
     */
    public function guard(Channel $channel, ?string $embedHost): void
    {
        if (! $this->isAllowed($channel, $embedHost)) {
            throw new AccessDeniedHttpException('embed host is not allowed for this channel');
        }
    }

    /**
     * 判断 embed host 是否在白名单中。
     * 白名单为空/未配置或包含 * 时视为不限制；配置后空 host 不放行。
     */
    #[\NoDiscard('忽略白名单判定结果等于没有校验')]
    public function isAllowed(Channel $channel, ?string $embedHost): bool
    {
        $allowList = $this->allowList($channel);
        if ($allowList === [] || in_array('*', $allowList, true)) {
            return true;
        }

        $normalized = $this->normalize($embedHost);
        if ($normalized === null) {
            return false;
        }

        foreach ($allowList as $allowed) {
            if ($this->matches($normalized, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 生成 CSP frame-ancestors 取值，语义与 isAllowed 对齐：
     * 白名单为空/未配置/含 * 时返回 "*"（不限制）；否则映射为 host-source 列表。
     * 白名单里的 ".example.com" 等价于 CSP 的 "*.example.com"。
     */
    public function frameAncestors(Channel $channel): string
    {
        $allowList = $this->allowList($channel);
        if ($allowList === [] || in_array('*', $allowList, true)) {
            return '*';
        }

        $sources = array_map(
            static fn (string $entry): string => str_starts_with($entry, '.') ? '*'.$entry : $entry,
            $allowList,
        );

        return implode(' ', $sources);
    }

    /**
     * 命中后把 embed host 写入 channels 行，便于运维观察。
     * 不在事务里、不刷新 updated_at，以免对装机行为产生意外副作用。
     *
     * 当 embed host 是 HelmDesk 自身 app host 时直接跳过，
     * 避免管理后台 WidgetTab 的 srcdoc 预览 iframe 每次切 tab 都污染装机指标。
     */
    public function record(Channel $channel, ?string $embedHost): void
    {
        $normalized = $this->normalize($embedHost);
        if ($normalized === null) {
            return;
        }

        if ($this->isAppOwnHost($normalized)) {
            return;
        }

        $updates = [
            'last_embed_host' => $normalized,
            'last_embed_at' => now(),
        ];

        if ($channel->first_embed_host === null) {
            $updates['first_embed_host'] = $normalized;
            $updates['first_embed_at'] = now();
        }

        $channel->forceFill($updates)->saveQuietly();
    }

    /**
     * 判断 host 是否与 config('app.url') 同主机（含端口）。
     *
     * embedHost 从 Origin / Referer 解析，格式形如 "host" 或 "host:port"。
     */
    private function isAppOwnHost(string $normalizedHost): bool
    {
        $baseUrl = Url::parse((string) config('app.url'));
        $host = $baseUrl?->getAsciiHost();
        if ($host === null || $host === '') {
            return false;
        }
        $port = $baseUrl->getPort();
        $authority = $port === null ? $host : $host.':'.$port;

        return $normalizedHost === $host || $normalizedHost === $authority;
    }

    /**
     * 从渠道设置中取已配置的白名单（小写化、去重、过滤非法值）。
     *
     * @return list<string>
     */
    private function allowList(Channel $channel): array
    {
        $settings = $channel->settings instanceof ChannelWebSettingsData
            ? $channel->settings
            : ChannelWebSettingsData::defaults();
        $raw = $settings->allowed_embed_hosts ?? [];

        $normalized = [];
        foreach ($raw as $entry) {
            $clean = strtolower(trim((string) $entry));
            if ($clean === '' || strlen($clean) > 253) {
                continue;
            }
            if ($clean === '*') {
                $normalized[$clean] = true;

                continue;
            }
            if (! preg_match('/^\*?\.?[a-z0-9.\-:]+$/', $clean)) {
                continue;
            }
            $normalized[$clean] = true;
        }

        return array_keys($normalized);
    }

    /**
     * 判断访客 embed host 是否匹配某条白名单：
     * - "example.com" 精确匹配
     * - "*.example.com" 匹配 a.example.com、a.b.example.com，不匹配 example.com 本身
     * - ".example.com" 等价于 "*.example.com"。
     */
    private function matches(string $host, string $pattern): bool
    {
        if ($pattern === '*') {
            return true;
        }

        if (str_starts_with($pattern, '*.')) {
            $suffix = substr($pattern, 1);

            return str_ends_with($host, $suffix) && $host !== ltrim($suffix, '.');
        }

        if (str_starts_with($pattern, '.')) {
            return str_ends_with($host, $pattern) && $host !== ltrim($pattern, '.');
        }

        return $host === $pattern;
    }
}
