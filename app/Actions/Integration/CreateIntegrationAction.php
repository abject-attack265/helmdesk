<?php

namespace App\Actions\Integration;

use App\Data\Integration\FormCreateIntegrationData;
use App\Models\Integration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 创建系统级集成记录。
 */
class CreateIntegrationAction
{
    use AsAction;

    /**
     * 创建新的集成，只保存配置，不触发远端连接或工具同步。
     */
    public function handle(FormCreateIntegrationData $data): Integration
    {
        $maxSort = Integration::query()->max('sort_order') ?? 0;

        /** @var Integration $integration */
        $integration = Integration::query()->create([
            'slug' => $this->generateSlug($data->name),
            'name' => $data->name,
            'provider' => $data->provider,
            'transport' => $data->provider->transport(),
            'endpoint_url' => $data->endpoint_url,
            'credentials' => Integration::buildAuthCredentials($data->auth_header_name, $data->auth_header_value),
            'headers' => Integration::normalizeHeaders($data->headers),
            'timeout_seconds' => $data->timeout_seconds ?? 30,
            'sort_order' => $maxSort + 1,
        ]);

        return $integration->refresh();
    }

    /**
     * 路由入口：校验管理员身份后创建并 302 回列表页。
     */
    public function asController(Request $request): RedirectResponse
    {
        Gate::authorize('app.owner');

        $data = FormCreateIntegrationData::from($request);
        $this->handle($data);

        return redirect()->route('app.manage.integrations.index');
    }

    /**
     * 生成唯一的集成标识。
     */
    private function generateSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'integration';
        }

        do {
            $candidate = $base.'-'.Str::lower(Str::random(6));
            $exists = Integration::query()->where('slug', $candidate)->exists();
        } while ($exists);

        return $candidate;
    }
}
