<?php

namespace App\Actions\AppSetting\AiProvider;

use App\Models\AiProvider;
use App\Services\AiRuntime\AiProviderConnectionChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 用一次最小化请求测试应用 AI 供应商凭据与网络连通。
 *
 * 复用 AiProviderConnectionChecker：挑一个启用中的 LLM 模型发一条极短消息，返回 JSON {success, message}。
 * 允许临时叠加未保存凭据 configuration，方便填了 key 没保存就先测。
 */
class CheckAiProviderAction
{
    use AsAction;

    public function __construct(
        private readonly AiProviderConnectionChecker $checker,
    ) {}

    /**
     * 测试已保存供应商，可叠加未保存凭据覆盖。
     *
     * @param  array<string, mixed>  $configuration
     * @return array{success: bool, message: string}
     */
    public function handle(string $providerId, array $configuration = []): array
    {
        $provider = AiProvider::query()->findOrFail($providerId);

        $credentials = $configuration === []
            ? ($provider->credentials ?? [])
            : $provider->mergeCredentials($configuration);

        $result = $this->checker->check($provider, $credentials);

        return [
            'success' => (bool) $result['success'],
            'message' => (string) $result['message'],
        ];
    }

    /**
     * 拆出可选 configuration override 后执行检测并以 JSON 返回。
     */
    public function asController(Request $request, string $provider): JsonResponse
    {
        $configuration = $request->input('configuration');

        return response()->json($this->handle(
            $provider,
            is_array($configuration) ? $configuration : [],
        ));
    }
}
