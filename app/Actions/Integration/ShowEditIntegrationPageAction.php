<?php

namespace App\Actions\Integration;

use App\Data\Integration\IntegrationData;
use App\Data\Integration\ShowEditIntegrationPagePropsData;
use App\Models\Integration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 打开集成编辑页面并下发表单初始值。
 */
class ShowEditIntegrationPageAction
{
    use AsAction;

    /**
     * 组装编辑集成页面 props。
     */
    public function handle(string $slug): ShowEditIntegrationPagePropsData
    {
        /** @var Integration $integration */
        $integration = Integration::query()
            ->with(['tools' => fn ($q) => $q->orderBy('name')])
            ->where('slug', $slug)
            ->firstOrFail();

        return new ShowEditIntegrationPagePropsData(
            server: IntegrationData::fromModel($integration),
        );
    }

    /**
     * 渲染编辑集成页面。
     */
    public function asController(Request $request, string $server): Response
    {
        Gate::authorize('app.owner');

        return Inertia::render('appSettings/integrations/Edit', $this->handle($server)->toArray());
    }
}
