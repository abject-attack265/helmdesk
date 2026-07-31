<?php

namespace App\Services\Mcp;

use App\Enums\IntegrationTransport;
use App\Models\Integration;
use Illuminate\Support\Facades\Log;
use NeuronAI\MCP\McpClient;
use RuntimeException;
use Throwable;

/**
 * 直接用 NeuronAI MCP 客户端做连通性测试与工具列表拉取。
 *
 * 无状态：每次按 server config + 凭据现建一个 StreamableHttp 传输的 McpClient（构造即完成 initialize 握手）。
 */
class McpRuntimeClient
{
    /**
     * 手动测试连接：仅完成 initialize 握手，不拉工具列表。
     *
     * @param  array<string, string>  $credentials
     * @param  array<string, string>|null  $headers
     * @return array{success: bool, code: string, message: string, supported: bool, warnings: array<int, string>}
     */
    public function checkServerConnection(Integration $server, array $credentials, ?array $headers = null): array
    {
        try {
            $this->makeClient($server, $credentials, $headers);

            return [
                'success' => true,
                'code' => 'check.succeeded',
                'message' => __('integration.runtime.check.succeeded'),
                'supported' => true,
                'warnings' => [],
            ];
        } catch (Throwable $exception) {
            Log::warning('MCP 连通性测试失败。', [
                'server_id' => $server->id,
                'message' => $this->sanitize($exception->getMessage()),
            ]);

            return [
                'success' => false,
                'code' => 'check.failed',
                'message' => __('integration.runtime.check.failed', ['error' => $this->sanitize($exception->getMessage())]),
                'supported' => true,
                'warnings' => [],
            ];
        }
    }

    /**
     * 拉取远端工具列表，由调用方对比写回 integration_tools 表。
     *
     * @param  array<string, string>  $credentials
     * @param  array<string, string>|null  $headers
     * @return array{success: bool, code: string, message: string, supported: bool, warnings: array<int, string>, tools: array<int, array<string, mixed>>}
     */
    public function listServerTools(Integration $server, array $credentials, ?array $headers = null): array
    {
        try {
            $client = $this->makeClient($server, $credentials, $headers);
            $tools = array_values(array_map($this->normalizeTool(...), $client->listTools()));

            return [
                'success' => true,
                'code' => 'list_tools.succeeded',
                'message' => __('integration.runtime.list_tools.succeeded'),
                'supported' => true,
                'warnings' => [],
                'tools' => $tools,
            ];
        } catch (Throwable $exception) {
            Log::warning('MCP 工具列表拉取失败。', [
                'server_id' => $server->id,
                'message' => $this->sanitize($exception->getMessage()),
            ]);

            return [
                'success' => false,
                'code' => 'list_tools.failed',
                'message' => __('integration.runtime.list_tools.failed', ['error' => $this->sanitize($exception->getMessage())]),
                'supported' => true,
                'warnings' => [],
                'tools' => [],
            ];
        }
    }

    /**
     * 按 server 配置和凭据创建 MCP 客户端（构造即握手）。
     *
     * @param  array<string, string>  $credentials
     * @param  array<string, string>|null  $headers
     */
    private function makeClient(Integration $server, array $credentials, ?array $headers): McpClient
    {
        if ($server->transport !== IntegrationTransport::StreamableHttp) {
            throw new RuntimeException(__('integration.runtime.validate.unsupported_transport', [
                'transport' => $server->transport->value,
            ]));
        }

        $endpoint = trim((string) $server->endpoint_url);
        if ($endpoint === '') {
            throw new RuntimeException(__('integration.runtime.validate.missing_endpoint'));
        }

        McpEndpointGuard::assertSafe($endpoint);

        $config = [
            'url' => $endpoint,
            'timeout' => (int) $server->timeout_seconds ?: 30,
        ];

        $allHeaders = $this->normalizeStringMap($headers ?? []);
        $authName = trim((string) ($credentials['auth_header_name'] ?? ''));
        $authValue = trim((string) ($credentials['auth_header_value'] ?? ''));
        if ($authName !== '' && $authValue !== '') {
            $allHeaders[$authName] = $authValue;
        }

        if ($allHeaders !== []) {
            $config['headers'] = $allHeaders;
        }

        return new McpClient($config);
    }

    /**
     * 把 NeuronAI listTools 的单个工具定义归一化成本地对账期望的 snake_case 结构。
     *
     * @param  array<string, mixed>  $tool
     * @return array<string, mixed>
     */
    private function normalizeTool(array $tool): array
    {
        return [
            'name' => (string) ($tool['name'] ?? ''),
            'description' => is_string($tool['description'] ?? null) ? $tool['description'] : null,
            'input_schema' => is_array($tool['inputSchema'] ?? null) ? $tool['inputSchema'] : null,
            'annotations' => is_array($tool['annotations'] ?? null) ? $tool['annotations'] : null,
        ];
    }

    /**
     * 丢弃非字符串键值并清理首尾空白。
     *
     * @param  array<string, mixed>  $map
     * @return array<string, string>
     */
    private function normalizeStringMap(array $map): array
    {
        $normalized = [];
        foreach ($map as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }

            $stringValue = trim((string) $value);
            if ($stringValue === '') {
                continue;
            }

            $normalized[$key] = $stringValue;
        }

        return $normalized;
    }

    /**
     * 脱敏并裁短上游错误，避免凭据进入返回消息。
     */
    private function sanitize(string $message): string
    {
        $patterns = [
            '/Bearer\s+[A-Za-z0-9._\-]+/i' => 'Bearer [redacted]',
            '/sk-[A-Za-z0-9_\-]{16,}/i' => '[redacted-key]',
        ];

        foreach ($patterns as $pattern => $replacement) {
            $message = (string) preg_replace($pattern, $replacement, $message);
        }

        return mb_substr($message, 0, 200);
    }
}
