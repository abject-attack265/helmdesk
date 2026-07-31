<?php

namespace App\Actions\Integration;

use App\Exceptions\BusinessException;
use App\Models\Integration;
use App\Models\IntegrationTool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 启用或停用集成工具供 AI 调用。
 *
 * 已下线的工具不能启用。
 */
class ToggleIntegrationToolAction
{
    use AsAction;

    /**
     * 翻转 is_enabled，下线工具拒绝启用。
     */
    public function handle(string $integrationSlug, string $toolId): IntegrationTool
    {
        /** @var Integration $integration */
        $integration = Integration::query()->where('slug', $integrationSlug)->firstOrFail();
        /** @var IntegrationTool $tool */
        $tool = $integration->tools()->where('id', $toolId)->firstOrFail();

        if (! $tool->is_enabled && $tool->removed_at !== null) {
            throw new BusinessException(__('integration.messages.tool_disabled_due_to_removal'));
        }

        $tool->is_enabled = ! $tool->is_enabled;
        $tool->save();

        return $tool;
    }

    /**
     * 校验系统所有者权限并更新工具状态。
     */
    public function asController(Request $request, string $server, string $tool): RedirectResponse
    {
        Gate::authorize('app.owner');

        $this->handle($server, $tool);

        return back();
    }
}
