<?php

namespace App\Services\Integration\Providers;

use RuntimeException;

/**
 * MCP 工具清单拉取失败时抛出，携带可写回 last_sync_* 的诊断码与已脱敏的人类可读消息。
 * 由 SyncIntegrationToolsAction 捕获并据此把集成置为同步失败状态。
 */
class McpToolListException extends RuntimeException
{
    /**
     * @param  string  $diagnosticCode  诊断码（如 list_tools.failed），写入日志便于排查
     * @param  string  $reason  已脱敏的失败原因，作为 last_sync_error 展示给用户
     */
    public function __construct(
        public readonly string $diagnosticCode,
        string $reason,
    ) {
        parent::__construct($reason);
    }
}
