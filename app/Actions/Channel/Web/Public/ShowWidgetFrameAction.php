<?php

namespace App\Actions\Channel\Web\Public;

use App\Services\LocalePreference;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 渲染网站渠道小部件 iframe 内部页面（/embed/widget/{code}）。
 *
 * 解析小部件启动数据并注入到 Blade 壳层，由 resources/js/widget.ts 接管渲染；
 * code 校验交给路由约束，embedHost 用于白名单校验与落库。
 */
class ShowWidgetFrameAction
{
    use AsAction;
    use ResolvesEmbedHost;
    use SendsEmbedFramingHeaders;

    /** 注入小部件启动数据解析 Action。 */
    public function __construct(
        private readonly ResolvePublicWebChannelWidgetBootstrapAction $resolve,
    ) {}

    /**
     * 解析渠道数据并返回小部件 iframe HTML 壳。
     */
    public function asController(Request $request, string $code): Response
    {
        $channel = $this->resolve->handle($code, $this->embedHostFromRequest($request));

        $bootstrapJson = json_encode(
            ['channel' => $channel->toArray()],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        $html = view('widget', [
            'lang' => LocalePreference::firstAcceptedLanguage($request->header('Accept-Language')) ?? 'und',
            'title' => $channel->site_name,
            'bootstrapJson' => $bootstrapJson,
        ])->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            ...$this->embedFramingHeaders($code),
            // 壳页内联按渠道/embedHost 变化的 bootstrap，并承载对哈希产物的引用：
            // 必须禁止 CDN/浏览器缓存，否则会指向旧资源或把渠道数据串到别处。
            'Cache-Control' => 'no-store',
        ]);
    }
}
