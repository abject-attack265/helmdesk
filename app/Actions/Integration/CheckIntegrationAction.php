<?php

namespace App\Actions\Integration;

use App\Data\Integration\FormCreateIntegrationData;
use App\Data\Integration\FormUpdateIntegrationData;
use App\Data\Integration\IntegrationConnectionCheckData;
use App\Models\Integration;
use App\Services\Integration\IntegrationProviderRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 使用当前表单配置测试集成连接并返回可展示结果。
 */
class CheckIntegrationAction
{
    use AsAction;

    /**
     * 注入集成供应商注册表。
     */
    public function __construct(
        private IntegrationProviderRegistry $registry,
    ) {}

    /**
     * 用当前表单配置触发连接测试，不落库。
     *
     * @return array{success: bool, code: string, message: string, supported: bool, warnings: array<int, string>}
     */
    public function handle(?string $slug, FormCreateIntegrationData|FormUpdateIntegrationData|null $data): array
    {
        $integration = $slug === null
            ? null
            : Integration::query()->where('slug', $slug)->firstOrFail();
        $runtimeIntegration = $data === null ? $integration : $this->integrationForRuntimeCheck($integration, $data);

        $result = $this->registry->for($runtimeIntegration->provider)->checkConnection($runtimeIntegration);

        if (! $result['success']) {
            throw ValidationException::withMessages([
                'endpoint_url' => $result['message'],
            ]);
        }

        return $result;
    }

    /**
     * 校验系统所有者权限并返回连接测试响应。
     */
    public function asController(Request $request, ?string $server = null): JsonResponse
    {
        Gate::authorize('app.owner');

        try {
            $data = match (true) {
                $server === null => FormCreateIntegrationData::from($request),
                $request->input() === [] => null,
                default => FormUpdateIntegrationData::from($request),
            };
            $this->handle($server, $data);
        } catch (ValidationException $e) {
            $message = (string) collect($e->errors())
                ->flatten()
                ->unique()
                ->implode("\n");

            return response()->json((new IntegrationConnectionCheckData(
                success: false,
                message: $message !== ''
                    ? $message
                    : __('integration.runtime.check.failed', ['error' => __('integration.runtime.bridge.request_failed')]),
            ))->toArray());
        }

        return response()->json((new IntegrationConnectionCheckData(
            success: true,
            message: __('integration.messages.check_succeeded'),
        ))->toArray());
    }

    /**
     * 基于当前表单配置构造临时集成，只用于运行时测试，不保存。
     */
    private function integrationForRuntimeCheck(?Integration $integration, FormCreateIntegrationData|FormUpdateIntegrationData $data): Integration
    {
        $runtimeIntegration = $integration === null ? new Integration : clone $integration;
        $runtimeIntegration->id = $integration?->id ?? '';
        $runtimeIntegration->slug = $integration?->slug ?? '';
        $runtimeIntegration->name = $data->name;
        if ($data instanceof FormCreateIntegrationData) {
            $runtimeIntegration->provider = $data->provider;
            $runtimeIntegration->transport = $data->provider->transport();
        }
        $runtimeIntegration->endpoint_url = $data->endpoint_url;
        $runtimeIntegration->headers = Integration::normalizeHeaders($data->headers);

        if ($data->timeout_seconds !== null) {
            $runtimeIntegration->timeout_seconds = $data->timeout_seconds;
        } elseif ($integration === null) {
            $runtimeIntegration->timeout_seconds = 30;
        }

        $runtimeIntegration->credentials = Integration::buildAuthCredentials($data->auth_header_name, $data->auth_header_value);

        return $runtimeIntegration;
    }
}
