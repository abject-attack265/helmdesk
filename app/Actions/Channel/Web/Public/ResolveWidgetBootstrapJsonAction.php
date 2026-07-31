<?php

namespace App\Actions\Channel\Web\Public;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 返回安装脚本所需的小部件外层入口配置 JSON（/embed/widget/{code}/bootstrap）。
 *
 * 按渠道 allowed_embed_hosts 决策 CORS 头——
 *  - "*"     白名单未配置，允许任意 Origin；
 *  - "match" 白名单已命中（白名单外来源已被业务 Action 403），回写访客实际 Origin。
 */
class ResolveWidgetBootstrapJsonAction
{
    use AsAction;
    use ResolvesEmbedHost;

    public function __construct(
        private readonly ResolvePublicWebChannelWidgetBootstrapAction $resolve,
    ) {}

    /**
     * 解析小部件启动数据并带上 CORS 头返回 JSON。
     */
    public function asController(Request $request, string $code): JsonResponse
    {
        $envelope = $this->resolve->resolveEnvelope($code, $this->embedHostFromRequest($request));

        $headers = [
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        switch ($envelope->cors_allow_origin) {
            case '*':
                $headers['Access-Control-Allow-Origin'] = '*';
                break;
            case 'match':
                $origin = trim((string) $request->header('Origin'));
                if ($origin !== '') {
                    $headers['Access-Control-Allow-Origin'] = $origin;
                    $headers['Vary'] = 'Origin';
                }
                break;
            default:
                // 取值超出契约说明约定被破坏，宁可显式失败也不要默认放开 CORS。
                throw new HttpException(500, 'Unexpected widget bootstrap CORS decision.');
        }

        return response()->json(['channel' => $envelope->channel->toArray()], 200, $headers);
    }
}
