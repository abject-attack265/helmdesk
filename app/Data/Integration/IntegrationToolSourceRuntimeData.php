<?php

namespace App\Data\Integration;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationTransport;
use App\Models\Integration;
use LogicException;
use Spatie\LaravelData\Data;

/**
 * 运行时可挂载的集成工具来源规格。
 * 由 ResolvePlanIntegrationToolSourcesAction（接待 agent）与 CollectActiveIntegrationToolSourcesAction（员工端 AI 助手）
 * 收集产出，IntegrationToolBuilder 据此按 provider 装配工具；随队列 job 序列化流转。
 *
 * provider 决定由哪个 IntegrationToolProvider 装配工具：
 *  - mcp：靠 tool_names 白名单 + 运行时 McpConnector 现场拉取工具定义，tools 留空；
 *  - business_system 等声明式 provider：tools 携带「同步时缓存下来的工具定义」（name/description/input_schema），
 *    运行时无需再访远端 list 即可装配，tool_names 仍作白名单。
 */
class IntegrationToolSourceRuntimeData extends Data
{
    /**
     * @param  array<string, string>  $credentials  认证凭据（auth_header_name / auth_header_value）
     * @param  array<string, string>  $headers  额外请求头
     * @param  list<string>  $tool_names  启用的工具白名单
     * @param  list<array{name: string, description: ?string, input_schema: ?array<string, mixed>}>  $tools  声明式 provider 携带的工具定义快照（mcp 留空）
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public IntegrationProvider $provider,
        public IntegrationTransport $transport,
        public string $endpoint_url,
        public array $credentials,
        public array $headers,
        public int $timeout_seconds,
        public array $tool_names,
        public array $tools = [],
    ) {}

    /**
     * 从集成模型和启用工具白名单生成运行时规格。
     *
     * 非 mcp 的声明式 provider 会把该 integration 当前 is_enabled 且未下线的工具定义（已随关系预加载）
     * 按白名单收窄后填入 tools，供运行时直接装配；mcp 不携带工具定义（tools 留空）。
     *
     * @param  list<string>  $toolNames
     */
    public static function fromIntegration(Integration $integration, array $toolNames): self
    {
        $provider = $integration->provider;
        if ($provider !== IntegrationProvider::Mcp && ! $integration->relationLoaded('tools')) {
            throw new LogicException('Integration tools relation must be loaded.');
        }

        return new self(
            id: (string) $integration->id,
            slug: (string) $integration->slug,
            name: (string) $integration->name,
            provider: $provider,
            transport: $integration->transport,
            endpoint_url: (string) $integration->endpoint_url,
            credentials: self::normalizeMap($integration->credentials ?? []),
            headers: self::normalizeMap($integration->headers ?? []),
            timeout_seconds: $integration->timeout_seconds,
            tool_names: $toolNames,
            tools: $provider === IntegrationProvider::Mcp
                ? []
                : self::collectToolDefinitions($integration, $toolNames),
        );
    }

    /**
     * 从已预加载的 integration tools 关系中，按白名单收窄出工具定义快照。
     * 仅供声明式 provider 使用，依赖调用方已用「is_enabled 且未下线」预加载 tools 关系。
     *
     * @param  list<string>  $toolNames
     * @return list<array{name: string, description: ?string, input_schema: ?array<string, mixed>}>
     */
    private static function collectToolDefinitions(Integration $integration, array $toolNames): array
    {
        $whitelist = array_flip($toolNames);

        $definitions = [];
        foreach ($integration->tools as $tool) {
            $name = (string) $tool->name;
            if ($name === '' || ! isset($whitelist[$name])) {
                continue;
            }

            $definitions[] = [
                'name' => $name,
                'description' => is_string($tool->description) ? $tool->description : null,
                'input_schema' => is_array($tool->input_schema) ? $tool->input_schema : null,
            ];
        }

        return $definitions;
    }

    /**
     * 把任意 key-value map 归一化为纯字符串 map：丢弃非标量值，trim 每个值，跳过空字符串。
     *
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private static function normalizeMap(array $values): array
    {
        $normalized = [];
        foreach ($values as $key => $value) {
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
}
