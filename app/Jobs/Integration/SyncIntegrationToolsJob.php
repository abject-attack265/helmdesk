<?php

namespace App\Jobs\Integration;

use App\Actions\Integration\SyncIntegrationToolsAction;
use App\Enums\IntegrationSyncStatus;
use App\Models\Integration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 集成工具缓存同步任务：单次处理一个集成。
 */
class SyncIntegrationToolsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 2;

    /**
     * 创建单个集成的工具同步任务。
     */
    public function __construct(public readonly string $integrationId)
    {
        $this->queue = 'background';
    }

    /**
     * 执行工具列表同步。
     */
    public function handle(SyncIntegrationToolsAction $syncAction): void
    {
        $integration = Integration::query()->find($this->integrationId);
        if ($integration === null) {
            Log::info('SyncIntegrationToolsJob: integration missing, skipped.', [
                'integration_id' => $this->integrationId,
            ]);

            return;
        }

        $syncAction->syncServer($integration);
    }

    /**
     * 记录队列最终失败，并把页面可见状态置为失败。
     */
    public function failed(Throwable $exception): void
    {
        Integration::query()
            ->whereKey($this->integrationId)
            ->update([
                'last_sync_status' => IntegrationSyncStatus::Failed,
                'last_sync_error' => $exception->getMessage(),
                'last_synced_at' => now(),
            ]);

        Log::warning('SyncIntegrationToolsJob failed.', [
            'integration_id' => $this->integrationId,
            'reason' => $exception->getMessage(),
        ]);
    }
}
