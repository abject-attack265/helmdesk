<?php

namespace App\Actions\AiChat;

use App\Data\Integration\IntegrationToolSourceRuntimeData;
use App\Enums\IntegrationSyncStatus;
use App\Models\Integration;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 收集配置完整、同步可用且含启用工具的集成来源。
 */
class CollectActiveIntegrationToolSourcesAction
{
    use AsAction;

    /**
     * 查询并归一化当前 app 下所有可用的集成及其工具白名单。
     *
     * @return list<IntegrationToolSourceRuntimeData>
     */
    public function handle(): array
    {
        $integrations = Integration::query()
            ->where('last_sync_status', '!=', IntegrationSyncStatus::Failed)
            ->whereNotNull('endpoint_url')
            ->where('endpoint_url', '!=', '')
            ->with(['tools' => function ($query): void {
                $query->where('is_enabled', true)->whereNull('removed_at');
            }])
            ->orderBy('sort_order')
            ->get();

        $payload = [];

        foreach ($integrations as $integration) {
            $toolNames = $integration->tools
                ->pluck('name')
                ->filter(fn ($name): bool => is_string($name) && $name !== '')
                ->values()
                ->all();

            if ($toolNames === []) {
                continue;
            }

            $payload[] = IntegrationToolSourceRuntimeData::fromIntegration($integration, $toolNames);
        }

        return $payload;
    }
}
