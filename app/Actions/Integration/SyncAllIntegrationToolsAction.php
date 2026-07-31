<?php

namespace App\Actions\Integration;

use App\Models\Integration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 异步更新系统内全部集成的工具记录。
 */
class SyncAllIntegrationToolsAction
{
    use AsAction;

    /**
     * 注入单个集成同步入队动作。
     */
    public function __construct(
        private readonly QueueIntegrationToolSyncAction $queueToolSync,
    ) {}

    /**
     * 将全部集成标记为更新中并逐个派发更新任务。
     *
     * @return array{queued: int}
     */
    public function handle(): array
    {
        $integrations = Integration::query()
            ->orderBy('sort_order')
            ->get(['id']);

        foreach ($integrations as $integration) {
            $this->queueToolSync->handle($integration);
        }

        return ['queued' => $integrations->count()];
    }

    /**
     * 路由入口：返回已入队数量，页面通过列表轮询观察每个集成状态。
     */
    public function asController(Request $request): JsonResponse
    {
        Gate::authorize('app.owner');

        $result = $this->handle();

        return response()->json([
            'success' => true,
            'message' => __('integration.messages.sync_all_queued', ['count' => $result['queued']]),
            'queued' => $result['queued'],
        ]);
    }
}
