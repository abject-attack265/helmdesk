<?php

namespace App\Actions\Channel\Web\Public;

use App\Services\LocalePreference;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 渲染访客独立聊天页（/ch/{code}）的 HTML 壳。
 *
 * 解析渠道启动数据并注入到 Blade 壳层，由 resources/js/standalone.ts 接管渲染；
 * code 校验交给路由约束，业务数据走 ResolvePublicWebChannelBootstrapAction。
 */
class ShowStandaloneChatPageAction
{
    use AsAction;
    use SendsEmbedFramingHeaders;

    /** 注入独立聊天页启动数据解析 Action。 */
    public function __construct(
        private readonly ResolvePublicWebChannelBootstrapAction $resolveBootstrap,
    ) {}

    /**
     * 解析渠道数据并返回独立页 HTML 壳。
     */
    public function asController(Request $request, string $code): Response
    {
        $channel = $this->resolveBootstrap->handle($code);

        $bootstrapJson = json_encode([
            'channel' => $channel->toArray(),
            'user_token' => $this->readUserToken($request),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return response()->view('standalone', [
            'lang' => LocalePreference::firstAcceptedLanguage($request->header('Accept-Language')) ?? 'und',
            'title' => $channel->site_name,
            'bootstrapJson' => $bootstrapJson,
        ])->withHeaders($this->embedFramingHeaders($code));
    }

    /**
     * 从 Authorization: Bearer 读取容器注入的签名访客身份；浏览器场景下通常为空。
     */
    private function readUserToken(Request $request): ?string
    {
        $token = $request->bearerToken();

        return is_string($token) && trim($token) !== '' ? trim($token) : null;
    }
}
