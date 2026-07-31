<?php

namespace App\Actions\Channel\Web\Public;

use Illuminate\Http\Request;
use Uri\WhatWg\Url;

/**
 * 从请求里提取嵌入小部件的宿主页域名。
 *
 * 优先 Origin 头，其次 Referer，按 WHATWG（浏览器同款）语义解析出小写 ASCII 主机名，解析失败返回 null。
 */
trait ResolvesEmbedHost
{
    /**
     * 解析嵌入宿主域名。
     */
    protected function embedHostFromRequest(Request $request): ?string
    {
        return $this->embedHostFromUrl($request->header('Origin'))
            ?? $this->embedHostFromUrl($request->header('Referer'));
    }

    /**
     * 解析 URL 字符串并返回小写 ASCII 主机名（IDN 域名做 punycode 归一），无法解析或为空时返回 null。
     */
    private function embedHostFromUrl(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $host = Url::parse($raw)?->getAsciiHost();

        return $host !== null && $host !== '' ? $host : null;
    }
}
