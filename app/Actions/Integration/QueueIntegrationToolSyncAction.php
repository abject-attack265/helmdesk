<?php

namespace App\Actions\Integration;

use App\Enums\IntegrationSyncStatus;
use App\Jobs\Integration\SyncIntegrationToolsJob;
use App\Models\Integration;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 将单个集成的工具同步任务放入队列。
 */
class QueueIntegrationToolSyncAction
{
    use AsAction;

    /**
     * 标记集成进入同步中状态，并派发工具同步任务。
     */
    public function handle(Integration $integration): void
    {
        Integration::query()
            ->whereKey($integration->id)
            ->update([
                'last_sync_status' => IntegrationSyncStatus::Syncing,
                'last_sync_error' => null,
            ]);

        SyncIntegrationToolsJob::dispatch((string) $integration->id)->afterCommit();
    }
}
