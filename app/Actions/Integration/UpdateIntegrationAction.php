<?php

namespace App\Actions\Integration;

use App\Data\Integration\FormUpdateIntegrationData;
use App\Models\Integration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 保存集成名称、服务地址、验证信息和自定义请求头。
 *
 * 验证请求头的名称和值均为空时清除已有验证信息。
 */
class UpdateIntegrationAction
{
    use AsAction;

    /**
     * 更新一个集成，只保存配置，不触发远端连接或工具同步。
     */
    public function handle(string $slug, FormUpdateIntegrationData $data): Integration
    {
        $integration = Integration::query()->where('slug', $slug)->firstOrFail();

        $integration->name = $data->name;
        $integration->endpoint_url = $data->endpoint_url;
        $integration->headers = Integration::normalizeHeaders($data->headers);

        if ($data->timeout_seconds !== null) {
            $integration->timeout_seconds = $data->timeout_seconds;
        }

        $integration->credentials = Integration::buildAuthCredentials($data->auth_header_name, $data->auth_header_value);
        $integration->save();

        return $integration->refresh();
    }

    /**
     * 校验系统所有者权限，保存后返回集成列表。
     */
    public function asController(Request $request, string $server): RedirectResponse
    {
        Gate::authorize('app.owner');

        $data = FormUpdateIntegrationData::from($request);
        $this->handle($server, $data);

        return redirect()->route('app.manage.integrations.index');
    }
}
