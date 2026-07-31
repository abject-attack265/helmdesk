<?php

namespace App\Actions\Integration;

use App\Enums\IntegrationSyncStatus;
use App\Models\Integration;
use App\Models\IntegrationTool;
use App\Services\Integration\IntegrationProviderRegistry;
use App\Services\Integration\Providers\BusinessSystemRequestException;
use App\Services\Integration\Providers\McpToolListException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 获取外部系统的工具列表并更新本地记录。
 *
 * 工具清单按 Integration.provider 交给对应 provider 获取，对账规则与 provider 无关：
 *  - 新工具：写入 is_enabled=true（默认启用）；
 *  - 已有工具：更新描述、结构和最后发现时间；
 *  - 清单中缺失的工具：标记下线并停用，保留已有引用；
 *  - 更新结果写入 last_sync_status 与 last_sync_error。
 */
class SyncIntegrationToolsAction
{
    /**
     * 注入 provider 注册表，按集成 provider 分发清单拉取。
     */
    public function __construct(
        private IntegrationProviderRegistry $registry,
    ) {}

    /**
     * 同步指定集成的工具列表，并回写该集成的同步状态。
     *
     * @return array{success: bool, code: string, message: string, total: int, added: int, removed: int, warnings: array<int, string>}
     */
    public function syncServer(Integration $integration): array
    {
        $integration->last_sync_status = IntegrationSyncStatus::Syncing;
        $integration->last_sync_error = null;
        $integration->save();

        try {
            $tools = $this->registry->for($integration->provider)->listToolDefinitions($integration);
        } catch (McpToolListException|BusinessSystemRequestException $exception) {
            $integration->last_sync_status = IntegrationSyncStatus::Failed;
            $integration->last_sync_error = $exception->getMessage();
            $integration->last_synced_at = now();
            $integration->save();

            Log::warning('集成工具更新失败。', [
                'integration_id' => $integration->id,
                'code' => $exception->diagnosticCode,
                'message' => $exception->getMessage(),
            ]);

            return [
                'success' => false,
                'code' => $exception->diagnosticCode,
                'message' => $exception->getMessage(),
                'total' => 0,
                'added' => 0,
                'removed' => 0,
                'warnings' => [],
            ];
        }

        $counts = DB::transaction(fn () => $this->reconcileTools($integration, $tools));

        $integration->last_sync_status = IntegrationSyncStatus::Success;
        $integration->last_sync_error = null;
        $integration->last_synced_at = now();
        $integration->save();

        return [
            'success' => true,
            'code' => 'list_tools.succeeded',
            'message' => __('integration.messages.sync_succeeded', [
                'total' => $counts['total'],
                'added' => $counts['added'],
                'removed' => $counts['removed'],
            ]),
            'total' => $counts['total'],
            'added' => $counts['added'],
            'removed' => $counts['removed'],
            'warnings' => [],
        ];
    }

    /**
     * 用外部系统返回的工具列表更新本地工具记录。
     *
     * @param  array<int, array<string, mixed>>  $remoteTools
     * @return array{total: int, added: int, removed: int}
     */
    private function reconcileTools(Integration $integration, array $remoteTools): array
    {
        $now = now();
        $existing = $integration->tools()->get()->keyBy('name');
        $seenNames = [];
        $added = 0;

        foreach ($remoteTools as $tool) {
            $name = (string) ($tool['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $seenNames[$name] = true;

            $description = is_string($tool['description'] ?? null) ? (string) $tool['description'] : null;
            $inputSchema = is_array($tool['input_schema'] ?? null) ? $tool['input_schema'] : null;
            $annotations = is_array($tool['annotations'] ?? null) ? $tool['annotations'] : null;

            /** @var IntegrationTool|null $current */
            $current = $existing->get($name);

            if ($current === null) {
                $integration->tools()->create([
                    'name' => $name,
                    'description' => $description,
                    'input_schema' => $inputSchema,
                    'annotations' => $annotations,
                    'is_enabled' => true,
                    'last_seen_at' => $now,
                    'removed_at' => null,
                ]);
                $added++;

                continue;
            }

            $current->description = $description;
            $current->input_schema = $inputSchema;
            $current->annotations = $annotations;
            $current->last_seen_at = $now;
            $current->removed_at = null;
            $current->save();
        }

        $removed = 0;
        foreach ($existing as $name => $tool) {
            if (isset($seenNames[$name])) {
                continue;
            }
            if ($tool->removed_at !== null) {
                continue;
            }
            $tool->removed_at = $now;
            $tool->is_enabled = false;
            $tool->save();
            $removed++;
        }

        return [
            'total' => count($remoteTools),
            'added' => $added,
            'removed' => $removed,
        ];
    }
}
