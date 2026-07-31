<?php

namespace App\Actions\Integration;

use App\Models\Integration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 删除指定集成及其工具记录。
 */
class DeleteIntegrationAction
{
    use AsAction;

    /**
     * 删除集成，工具记录在事务里一并清理。
     */
    public function handle(string $slug): void
    {
        $integration = Integration::query()->where('slug', $slug)->firstOrFail();

        DB::transaction(function () use ($integration): void {
            $integration->tools()->delete();
            $integration->delete();
        });
    }

    /**
     * 校验系统所有者权限并删除集成。
     */
    public function asController(Request $request, string $server): RedirectResponse
    {
        Gate::authorize('app.owner');

        $this->handle($server);

        return back();
    }
}
