<?php

namespace App\Actions\Reception\Plan;

use App\Data\Integration\IntegrationToolSourceRuntimeData;
use App\Data\Reception\Plan\CompiledIntegrationGrantData;
use App\Enums\IntegrationSyncStatus;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 按接待方案的集成授权快照解析运行时可挂载的集成工具来源列表。
 *
 * 以「方案级 integration 授权」为入口：逐个加载授权的 integration（过滤本应用、上次同步未失败、
 * endpoint 非空），把白名单收窄成「白名单 ∩ 该 integration 当前 is_enabled 且未下线的工具名」
 * （白名单为空则取全部已启用工具名）；无可用工具的授权跳过。
 * 产出的工具来源规格直接下发给接待 agent 运行时挂载。
 */
class ResolvePlanIntegrationToolSourcesAction
{
    use AsAction;

    /**
     * 按集成授权快照解析工具来源，丢弃不可用或不归属当前应用的授权 / 工具。
     *
     * @param  list<CompiledIntegrationGrantData>  $grants
     * @return list<IntegrationToolSourceRuntimeData>
     */
    public function handle(array $grants): array
    {
        if ($grants === []) {
            return [];
        }

        $integrationIds = array_values(array_unique(array_map(
            static fn (CompiledIntegrationGrantData $grant): string => $grant->integration_id,
            $grants,
        )));

        /** @var array<string, Integration> $integrations */
        $integrations = Integration::query()
            ->whereIn('id', $integrationIds)

            ->where('last_sync_status', '!=', IntegrationSyncStatus::Failed)
            ->whereNotNull('endpoint_url')
            ->where('endpoint_url', '!=', '')
            ->with(['tools' => function ($query): void {
                $query->where('is_enabled', true)->whereNull('removed_at');
            }])
            ->get()
            ->keyBy(fn (Integration $integration): string => (string) $integration->id);

        $payload = [];
        foreach ($grants as $grant) {
            $integration = $integrations->get($grant->integration_id);
            if ($integration === null) {
                $this->warnUnavailableIntegration($grant->integration_id);

                continue;
            }

            $enabledToolNames = $integration->tools
                ->pluck('name')
                ->filter(fn ($name): bool => is_string($name) && $name !== '')
                ->values()
                ->all();

            $toolNames = $grant->tool_names === []
                ? $enabledToolNames
                : array_values(array_intersect($grant->tool_names, $enabledToolNames));

            if ($toolNames === []) {
                $this->warnUnavailableTools($grant->integration_id, $grant->tool_names);

                continue;
            }

            $payload[] = IntegrationToolSourceRuntimeData::fromIntegration($integration, $toolNames);
        }

        return $payload;
    }

    /**
     * 授权的集成在运行时整台不可用（不属于应用、上次同步失败或 endpoint 缺失）时记录警告，
     * 让接待能力的"静默缩水"在日志中可见。
     */
    private function warnUnavailableIntegration(string $integrationId): void
    {
        Log::warning('接待方案授权的集成运行时不可用，已跳过。', [
            'integration_id' => $integrationId,
        ]);
    }

    /**
     * 授权的集成可用但白名单内的工具全部不可用（被禁用 / 下线）时记录警告。
     *
     * @param  list<string>  $requestedToolNames
     */
    private function warnUnavailableTools(string $integrationId, array $requestedToolNames): void
    {
        Log::warning('接待方案授权的集成无可用工具，已跳过。', [
            'integration_id' => $integrationId,
            'requested_tool_names' => $requestedToolNames,
        ]);
    }
}
