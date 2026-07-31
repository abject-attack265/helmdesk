<?php

namespace App\Actions\Integration;

use App\Data\EnumOptionData;
use App\Data\Integration\ShowCreateIntegrationPagePropsData;
use App\Enums\IntegrationProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 打开集成创建页面并下发可选类型。
 */
class ShowCreateIntegrationPageAction
{
    use AsAction;

    /**
     * 组装创建集成页面 props。
     */
    public function handle(): ShowCreateIntegrationPagePropsData
    {
        return new ShowCreateIntegrationPagePropsData(
            provider_options: EnumOptionData::fromCases(IntegrationProvider::userSelectableCases()),
        );
    }

    /**
     * 渲染创建集成页面。
     */
    public function asController(Request $request): Response
    {
        Gate::authorize('app.owner');

        return Inertia::render('appSettings/integrations/Create', $this->handle()->toArray());
    }
}
