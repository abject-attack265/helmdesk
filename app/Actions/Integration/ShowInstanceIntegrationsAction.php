<?php

namespace App\Actions\Integration;

use App\Data\Integration\IntegrationData;
use App\Data\Integration\ShowInstanceIntegrationsPagePropsData;
use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 加载系统集成和工具列表页面数据。
 */
class ShowInstanceIntegrationsAction
{
    use AsAction;

    /**
     * 装配集成列表和工具明细。
     */
    public function handle(): ShowInstanceIntegrationsPagePropsData
    {
        $integrations = Integration::query()
            ->with(['tools' => fn ($q) => $q->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Integration $integration) => IntegrationData::fromModel($integration))
            ->all();

        return new ShowInstanceIntegrationsPagePropsData(
            servers: $integrations,
        );
    }

    /**
     * 校验系统所有者权限并渲染集成列表页。
     */
    public function asController(Request $request): Response
    {
        Gate::authorize('app.owner');

        return Inertia::render('appSettings/integrations/Index', $this->handle()->toArray());
    }
}
