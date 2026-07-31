<?php

namespace App\Services\Integration\Providers;

use App\Contracts\Integration\IntegrationToolProvider;
use App\Data\Integration\IntegrationToolContext;
use App\Data\Integration\IntegrationToolSourceRuntimeData;
use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Services\Mcp\McpEndpointGuard;
use App\Services\Mcp\McpRuntimeClient;
use Illuminate\Support\Facades\Log;
use NeuronAI\MCP\McpConnector;
use NeuronAI\Tools\Tool;

/**
 * MCP provider：接 Model Context Protocol 服务（Streamable HTTP 传输）。
 *
 * - 同步：用 McpRuntimeClient 完成 initialize 握手并拉取远端工具清单；
 * - 运行时：用 NeuronAI McpConnector 现场拉取工具定义并装配（靠 tool_names 白名单收窄），
 *   发起请求前过 McpEndpointGuard SSRF 兜底（防 DNS 重绑定绕过保存时校验）。
 */
class McpToolProvider implements IntegrationToolProvider
{
    /**
     * 注入 MCP 运行时客户端（同步阶段拉取工具清单用）。
     */
    public function __construct(
        private McpRuntimeClient $runtimeClient,
    ) {}

    /**
     * 本 provider 对应 IntegrationProvider::Mcp。
     */
    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::Mcp;
    }

    /**
     * 用 McpRuntimeClient 完成握手探测连通性。
     *
     * @return array{success: bool, code: string, message: string, supported: bool, warnings: array<int, string>}
     */
    public function checkConnection(Integration $integration): array
    {
        return $this->runtimeClient->checkServerConnection(
            $integration,
            $integration->credentials ?? [],
            $integration->headers,
        );
    }

    /**
     * 用 McpRuntimeClient 拉取远端工具清单；上游不可用时抛异常中断同步（由调用方写回失败状态）。
     *
     * @return list<array{name: string, description: ?string, input_schema: ?array<string, mixed>, annotations: ?array<string, mixed>}>
     */
    public function listToolDefinitions(Integration $integration): array
    {
        $result = $this->runtimeClient->listServerTools(
            $integration,
            $integration->credentials ?? [],
            $integration->headers,
        );

        if (! $result['success']) {
            throw new McpToolListException($result['code'], $result['message']);
        }

        return array_values($result['tools']);
    }

    /**
     * 用 McpConnector 现场拉取并装配工具；空端点或指向内网/保留地址的端点会被跳过。
     *
     * MCP 工具按其自身协议交互，无「联系人 / 会话身份上下文」语义，故忽略 $context。
     *
     * @return list<Tool>
     */
    public function buildRuntimeTools(IntegrationToolSourceRuntimeData $source, ?IntegrationToolContext $context = null): array
    {
        $endpoint = trim($source->endpoint_url);
        if ($endpoint === '') {
            return [];
        }

        // SSRF 兜底：跳过指向内网/保留地址的 MCP 端点（防 DNS 重绑定绕过保存时校验）。
        if (! McpEndpointGuard::isSafe($endpoint)) {
            Log::warning('MCP 端点指向受限地址，运行时已跳过。', ['endpoint' => $endpoint]);

            return [];
        }

        $config = ['url' => $endpoint, 'timeout' => $source->timeout_seconds];
        $headers = $source->headers;
        $authName = trim($source->credentials['auth_header_name'] ?? '');
        $authValue = trim($source->credentials['auth_header_value'] ?? '');
        if ($authName !== '' && $authValue !== '') {
            $headers[$authName] = $authValue;
        }
        if ($headers !== []) {
            $config['headers'] = $headers;
        }

        $connector = new McpConnector($config);
        if ($source->tool_names !== []) {
            $connector = $connector->only($source->tool_names);
        }

        $tools = [];
        foreach ($connector->tools() as $tool) {
            $tools[] = $tool;
        }

        return $tools;
    }
}
