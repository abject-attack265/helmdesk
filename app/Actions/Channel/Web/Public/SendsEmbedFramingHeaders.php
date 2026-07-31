<?php

namespace App\Actions\Channel\Web\Public;

use App\Enums\ChannelType;
use App\Models\Channel;
use App\Services\Channel\WebChannelEmbedHostGate;

/**
 * 为可被第三方站点跨域嵌入的 embed / standalone 响应构造 framing 响应头。
 *
 * - Content-Security-Policy: frame-ancestors —— 真正的管控项，按渠道 allowed_embed_hosts
 *   下发，浏览器据此放行/拦截把本页当 iframe 嵌入的祖先页面。现代浏览器在响应带
 *   frame-ancestors 时会忽略 X-Frame-Options（CSP2+ 规范），因此即便上游 CDN/代理强行
 *   注入 X-Frame-Options: SAMEORIGIN，最终也以此处的 frame-ancestors 为准。
 * - X-Frame-Options: ALLOWALL —— 占位用。X-Frame-Options 只有 DENY/SAMEORIGIN，无法表达
 *   "按域名放行"；这里给一个浏览器会忽略的非法值，避免上游 CDN 在"源站未设置时才补
 *   SAMEORIGIN"的策略下把响应顶掉。实际管控仍由上面的 frame-ancestors 负责。
 */
trait SendsEmbedFramingHeaders
{
    /**
     * @return array<string, string>
     */
    protected function embedFramingHeaders(string $code): array
    {
        $channel = Channel::query()
            ->withTrashed()
            ->where('code', $code)
            ->where('type', ChannelType::Web)
            ->first();

        $frameAncestors = $channel === null
            ? '*'
            : app(WebChannelEmbedHostGate::class)->frameAncestors($channel);

        return [
            'Content-Security-Policy' => 'frame-ancestors '.$frameAncestors,
            'X-Frame-Options' => 'ALLOWALL',
        ];
    }
}
