<?php

namespace App\Contracts\Integration;

use App\Data\Integration\IntegrationToolContext;
use App\Data\Integration\IntegrationToolSourceRuntimeData;
use App\Enums\IntegrationProvider;
use App\Models\Integration;
use NeuronAI\Tools\Tool;

/**
 * 集成工具 provider 的能力契约。
 *
 * 业务层依赖这个抽象做两件事：按 provider「同步远端工具清单」（写回 integration_tools）与
 * 「装配运行时工具」（挂载到 AI agent）。每种接入平台（MCP / 自有业务系统 / 后续电商平台）实现本接口，
 * 由 IntegrationProviderRegistry 按 Integration.provider 解析对应实例；新增平台只实现本接口、在注册表登记即可，
 * 同步 Action 与工具构建器无需感知具体协议。
 */
interface IntegrationToolProvider
{
    /**
     * 本 provider 对应的 IntegrationProvider 枚举值（注册表据此分发）。
     */
    public function provider(): IntegrationProvider;

    /**
     * 测试与外部系统的连通性，结果用于「连接测试」反馈。
     * 各 provider 按自身协议探测（MCP 走握手、业务系统探 manifest 端点）；不抛异常，失败以 success=false 表达。
     *
     * @return array{success: bool, code: string, message: string, supported: bool, warnings: array<int, string>}
     */
    public function checkConnection(Integration $integration): array;

    /**
     * 拉取远端工具清单，供 SyncIntegrationToolsAction 增量写回 integration_tools。
     *
     * @return list<array{name: string, description: ?string, input_schema: ?array<string, mixed>, annotations: ?array<string, mixed>}>
     */
    public function listToolDefinitions(Integration $integration): array;

    /**
     * 把运行时工具来源规格装配成可挂载到 agent 的 NeuronAI 工具集。
     *
     * $context 是工具调用时的运行时身份上下文（当前联系人 external_id / 会话 id）：声明式 provider
     * （业务系统）把它带进 invoke 请求体的 context；MCP 无此语义故忽略。null 表示无身份上下文（如员工端助手）。
     *
     * @return list<Tool>
     */
    public function buildRuntimeTools(IntegrationToolSourceRuntimeData $source, ?IntegrationToolContext $context = null): array;
}
