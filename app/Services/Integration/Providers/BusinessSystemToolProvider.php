<?php

namespace App\Services\Integration\Providers;

use App\Contracts\Integration\IntegrationToolProvider;
use App\Contracts\Integration\ProvidesContactPanel;
use App\Data\Integration\IntegrationToolContext;
use App\Data\Integration\IntegrationToolSourceRuntimeData;
use App\Data\Integration\Panel\ContactPanelData;
use App\Enums\IdentityType;
use App\Enums\IntegrationProvider;
use App\Models\Contact;
use App\Models\Integration;
use App\Services\Integration\Providers\Concerns\BuildsToolFromSchema;
use App\Services\Mcp\McpEndpointGuard;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use NeuronAI\Tools\Tool;
use Throwable;

/**
 * 自有业务系统 provider：把系统外部的订单、客户、工单等能力接入客服和 AI。
 *
 * ## 接入契约（业务系统须实现以下三个 HTTP/JSON 端点）
 *
 * base_url 取 Integration.endpoint_url；鉴权复用 Integration.credentials 的 auth_header_name /
 * auth_header_value（一个自定义鉴权 header，随每次请求发送），并附带 Integration.headers 里的自定义请求头；
 * 请求超时取 Integration.timeout_seconds。base_url 在发起请求前过 McpEndpointGuard::isSafe 做 SSRF 兜底。
 *
 * ### 1. 工具清单（供同步）
 * `GET {base_url}/helmdesk/tools`
 * 响应（200）：
 * ```json
 * {
 *   "tools": [
 *     {
 *       "name": "query_order",
 *       "description": "按订单号或邮箱查订单",
 *       "input_schema": { "type": "object", "properties": { ... }, "required": [ ... ] }
 *     }
 *   ]
 * }
 * ```
 * input_schema 为标准 JSON Schema object（properties / required / 各属性的 type/description/enum/items）。
 *
 * ### 2. 执行工具（运行时 AI 调用）
 * `POST {base_url}/helmdesk/tools/{name}/invoke`
 * 请求体：
 * ```json
 * {
 *   "arguments": { ...LLM 按 input_schema 填的参数... },
 *   "context": { "contact_external_id": string|null, "email": string|null, "conversation_id": string|null }
 * }
 * context 提供多种识别客户的途径：业务系统可按 contact_external_id 精确匹配，也可在缺失时退而按 email 识别。
 * ```
 * 响应（200）：
 * ```json
 * { "content": "给 AI 的文本/结构化结论（字符串）", "data": { ...可选结构化... } }
 * ```
 * content 为面向 AI 的最终结论字符串（必填）；data 为可选的结构化补充（本实现仅把 content 回传给 AI）。
 *
 * ### 3. 联系人业务数据面板（人工接待侧边栏）
 * `GET {base_url}/helmdesk/contact-panel?external_id={外部用户ID}&email={主邮箱}`
 * 业务系统据 external_id / email 查到此人后，**直接吐统一展示描述符**（它最懂自己的数据如何呈现），
 * provider 透传映射为 ContactPanelData。响应（200）：
 * ```json
 * {
 *   "sections": [
 *     {
 *       "title": "客户概况",
 *       "collapsed": false,
 *       "blocks": [
 *         { "kind": "key_value", "rows": [ { "label": "等级", "value": "VIP", "value_type": "badge" } ] },
 *         { "kind": "list", "title": "最近订单", "items": [
 *           { "title": "#SO-1024", "subtitle": "已发货", "meta": ["¥299", "2026-06-10"], "badge": "普通" }
 *         ] }
 *       ]
 *     }
 *   ]
 * }
 * ```
 * 积木词汇表：block.kind ∈ {key_value, list}；key_value 用 rows（label/value/value_type，
 * value_type ∈ {text,date,money,link,badge}），list 用 items（title/subtitle/meta[]/badge/link）。
 * 无匹配此联系人时返回 `{"sections": []}` 或 404；两者都被映射为「无面板」（null），接待页整区不渲染。
 *
 * ### 错误约定
 * tools：非 2xx 或清单格式错误会中断更新并保留现有工具记录。
 * invoke：任意非 2xx 响应视为该工具本次不可用：运行时不抛异常打断整轮对话，而是返回结构化错误
 * `{"error": "tool_unavailable"}` 并记 warning 日志，让 AI 自行决定降级措辞。
 * contact-panel：404 或空 sections 表示没有面板；其他非 2xx 和异常记 warning 日志并返回 null。
 */
class BusinessSystemToolProvider implements IntegrationToolProvider, ProvidesContactPanel
{
    use BuildsToolFromSchema;

    /**
     * 工具清单端点路径。
     */
    private const string MANIFEST_PATH = '/helmdesk/tools';

    /**
     * 工具执行端点路径模板（:name 为工具名）。
     */
    private const string INVOKE_PATH = '/helmdesk/tools/%s/invoke';

    /**
     * 联系人业务数据面板端点路径。
     */
    private const string CONTACT_PANEL_PATH = '/helmdesk/contact-panel';

    /**
     * 本 provider 对应 IntegrationProvider::BusinessSystem。
     */
    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::BusinessSystem;
    }

    /**
     * 探测连通性：GET manifest 端点，2xx 视为连通；401/403 视为认证失败。不抛异常。
     *
     * @return array{success: bool, code: string, message: string, supported: bool, warnings: array<int, string>}
     */
    public function checkConnection(Integration $integration): array
    {
        $baseUrl = rtrim(trim((string) $integration->endpoint_url), '/');
        if ($baseUrl === '' || ! McpEndpointGuard::isSafe($baseUrl)) {
            return $this->checkFailure('check.unsafe_endpoint', __('integration.runtime.validate.unsafe_endpoint'));
        }

        try {
            $response = $this->httpClient(
                $baseUrl,
                $integration->credentials ?? [],
                $integration->headers,
                $integration->timeout_seconds,
            )->get($baseUrl.self::MANIFEST_PATH);
        } catch (Throwable $exception) {
            return $this->checkFailure('check.failed', __('integration.runtime.check.failed', ['error' => $exception->getMessage()]));
        }

        if (in_array($response->status(), [401, 403], true)) {
            return $this->checkFailure('check.unauthorized', __('integration.runtime.check.unauthorized'));
        }

        if (! $response->successful()) {
            return $this->checkFailure('check.failed', __('integration.runtime.check.failed', ['error' => 'HTTP '.$response->status()]));
        }

        return [
            'success' => true,
            'code' => 'check.succeeded',
            'message' => __('integration.runtime.check.succeeded'),
            'supported' => true,
            'warnings' => [],
        ];
    }

    /**
     * 构造一份连通性测试失败结果。
     *
     * @return array{success: bool, code: string, message: string, supported: bool, warnings: array<int, string>}
     */
    private function checkFailure(string $code, string $message): array
    {
        return [
            'success' => false,
            'code' => $code,
            'message' => $message,
            'supported' => true,
            'warnings' => [],
        ];
    }

    /**
     * GET manifest 拉取工具清单并解析为同步可写回的结构。
     *
     * @return list<array{name: string, description: ?string, input_schema: ?array<string, mixed>, annotations: ?array<string, mixed>}>
     */
    public function listToolDefinitions(Integration $integration): array
    {
        $baseUrl = rtrim(trim((string) $integration->endpoint_url), '/');
        if ($baseUrl === '' || ! McpEndpointGuard::isSafe($baseUrl)) {
            throw new BusinessSystemRequestException(
                'business_system.unsafe_endpoint',
                __('integration.runtime.validate.unsafe_endpoint'),
            );
        }

        try {
            $response = $this->httpClient(
                $baseUrl,
                $integration->credentials ?? [],
                $integration->headers,
                $integration->timeout_seconds,
            )->get($baseUrl.self::MANIFEST_PATH);
        } catch (Throwable $exception) {
            throw new BusinessSystemRequestException('business_system.list_tools.failed', $exception->getMessage());
        }

        if (! $response->successful()) {
            throw new BusinessSystemRequestException(
                'business_system.list_tools.failed',
                __('integration.runtime.list_tools.failed', ['error' => 'HTTP '.$response->status()]),
            );
        }

        $tools = $response->json('tools');
        if (! is_array($tools) || ! array_is_list($tools)) {
            throw $this->invalidToolList();
        }

        $definitions = [];
        $seenNames = [];
        foreach ($tools as $tool) {
            if (! is_array($tool)) {
                throw $this->invalidToolList();
            }

            $name = is_string($tool['name'] ?? null) ? trim($tool['name']) : '';
            if (
                $name === ''
                || isset($seenNames[$name])
                || (array_key_exists('description', $tool) && $tool['description'] !== null && ! is_string($tool['description']))
                || (array_key_exists('input_schema', $tool) && $tool['input_schema'] !== null && ! is_array($tool['input_schema']))
                || (array_key_exists('annotations', $tool) && $tool['annotations'] !== null && ! is_array($tool['annotations']))
            ) {
                throw $this->invalidToolList();
            }
            $seenNames[$name] = true;

            $definitions[] = [
                'name' => $name,
                'description' => is_string($tool['description'] ?? null) ? $tool['description'] : null,
                'input_schema' => is_array($tool['input_schema'] ?? null) ? $tool['input_schema'] : null,
                'annotations' => is_array($tool['annotations'] ?? null) ? $tool['annotations'] : null,
            ];
        }

        return $definitions;
    }

    /**
     * 构造工具清单格式错误异常。
     */
    private function invalidToolList(): BusinessSystemRequestException
    {
        return new BusinessSystemRequestException(
            'business_system.list_tools.invalid_response',
            __('integration.runtime.list_tools.invalid_response'),
        );
    }

    /**
     * GET contact-panel 拉取该联系人的业务数据描述符并透传映射为 ContactPanelData。
     *
     * 取联系人主邮箱与外部 ID 身份值作为 query；联系人无外部 ID 身份时不下发（仅按 email 查）。
     * 404 和空 sections 表示无面板；端点不安全、其他非 2xx 和异常记 warning 并返回 null。
     */
    public function buildContactPanel(Integration $integration, Contact $contact): ?ContactPanelData
    {
        $baseUrl = rtrim(trim((string) $integration->endpoint_url), '/');
        if ($baseUrl === '' || ! McpEndpointGuard::isSafe($baseUrl)) {
            Log::warning('业务系统端点为空或指向受限地址，联系人面板已跳过。', [
                'integration' => $integration->id,
                'endpoint' => $baseUrl,
            ]);

            return null;
        }

        $contact->loadMissing('identities');
        $externalId = $contact->identities
            ->first(static fn ($identity): bool => $identity->type === IdentityType::ExternalId)
            ?->value;

        $query = array_filter([
            'external_id' => is_string($externalId) && $externalId !== '' ? $externalId : null,
            'email' => is_string($contact->primary_email) && $contact->primary_email !== '' ? $contact->primary_email : null,
        ], static fn (?string $value): bool => $value !== null);

        try {
            $response = $this->httpClient(
                $baseUrl,
                $integration->credentials ?? [],
                $integration->headers,
                $integration->timeout_seconds,
            )->get($baseUrl.self::CONTACT_PANEL_PATH, $query);
        } catch (Throwable $exception) {
            Log::warning('业务系统联系人面板请求异常。', [
                'integration' => $integration->id,
                'endpoint' => $baseUrl,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if ($response->status() === 404) {
            return null;
        }

        if (! $response->successful()) {
            Log::warning('业务系统联系人面板返回非 2xx。', [
                'integration' => $integration->id,
                'endpoint' => $baseUrl,
                'status' => $response->status(),
            ]);

            return null;
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            return null;
        }

        return ContactPanelData::tryFromBusinessSystemPayload(
            $payload,
            (string) $integration->id,
            (string) $integration->name,
            $integration->provider->label(),
        );
    }

    /**
     * 把 source 携带的每个工具定义装配成 NeuronAI Tool：input_schema → ToolProperty 列表，
     * callable POST 到 invoke 端点。空端点或指向内网/保留地址的端点会被整体跳过。
     *
     * $context 携带联系人外部 ID、主邮箱与会话 id，invoke 时随请求体下发。
     * 为 null（如员工端助手无客户语境）时下发的 context 字段全为 null。
     *
     * @return list<Tool>
     */
    public function buildRuntimeTools(IntegrationToolSourceRuntimeData $source, ?IntegrationToolContext $context = null): array
    {
        $baseUrl = rtrim(trim($source->endpoint_url), '/');
        if ($baseUrl === '') {
            return [];
        }

        // SSRF 兜底：跳过指向内网/保留地址的端点（防 DNS 重绑定绕过保存时校验）。
        if (! McpEndpointGuard::isSafe($baseUrl)) {
            Log::warning('业务系统端点指向受限地址，运行时已跳过。', ['endpoint' => $baseUrl]);

            return [];
        }

        $whitelist = $source->tool_names === [] ? null : array_flip($source->tool_names);

        $tools = [];
        foreach ($source->tools as $definition) {
            $name = is_string($definition['name'] ?? null) ? trim($definition['name']) : '';
            if ($name === '') {
                continue;
            }
            if ($whitelist !== null && ! isset($whitelist[$name])) {
                continue;
            }

            $tools[] = $this->buildToolFromSchema($name, $definition, $this->buildInvoker($source, $baseUrl, $name, $context));
        }

        return $tools;
    }

    /**
     * 构造工具执行回调。
     *
     * NeuronAI Tool::execute() 用 `call_user_func($callback, ...$parameters)` 调用，其中 $parameters 是
     * 「按 addProperty 顺序的属性名 => LLM 入参值」关联数组；PHP 对字符串键的解包等同于命名实参，
     * 故 `function (...$args)` 收到的 $args 即「属性名 => 值」关联数组（与 NeuronAI 自带的 CallableMcpTool 同构）。
     */
    private function buildInvoker(IntegrationToolSourceRuntimeData $source, string $baseUrl, string $name, ?IntegrationToolContext $context): callable
    {
        $credentials = $source->credentials;
        $headers = $source->headers;
        $timeout = $source->timeout_seconds;
        // 预先取出标量身份值闭包捕获，避免在 Octane 长驻进程里捕获可变 Data 实例。
        $contactExternalId = $context?->contact_external_id;
        $conversationId = $context?->conversation_id;
        $email = $context?->email;

        return function (...$args) use ($baseUrl, $name, $credentials, $headers, $timeout, $contactExternalId, $email, $conversationId): string {
            try {
                $response = $this->httpClient($baseUrl, $credentials, $headers, $timeout)
                    ->post(sprintf($baseUrl.self::INVOKE_PATH, rawurlencode($name)), [
                        'arguments' => (object) $args,
                        'context' => [
                            'contact_external_id' => $contactExternalId,
                            'email' => $email,
                            'conversation_id' => $conversationId,
                        ],
                    ]);
            } catch (Throwable $exception) {
                Log::warning('业务系统工具调用异常。', [
                    'endpoint' => $baseUrl,
                    'tool' => $name,
                    'message' => $exception->getMessage(),
                ]);

                return (string) json_encode(['error' => 'tool_unavailable']);
            }

            if (! $response->successful()) {
                Log::warning('业务系统工具调用返回非 2xx。', [
                    'endpoint' => $baseUrl,
                    'tool' => $name,
                    'status' => $response->status(),
                ]);

                return (string) json_encode(['error' => 'tool_unavailable']);
            }

            $content = $response->json('content');

            return is_string($content) ? $content : (string) json_encode(['error' => 'tool_unavailable']);
        };
    }

    /**
     * 构造带鉴权 header + 自定义请求头 + 超时的 HTTP 客户端。
     *
     * @param  array<string, string>  $credentials
     * @param  array<string, string>|null  $headers
     */
    private function httpClient(string $baseUrl, array $credentials, ?array $headers, int $timeout): PendingRequest
    {
        $allHeaders = [];
        foreach ($headers ?? [] as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $allHeaders[$key] = (string) $value;
            }
        }

        $authName = trim((string) ($credentials['auth_header_name'] ?? ''));
        $authValue = trim((string) ($credentials['auth_header_value'] ?? ''));
        if ($authName !== '' && $authValue !== '') {
            $allHeaders[$authName] = $authValue;
        }

        return Http::asJson()
            ->acceptJson()
            ->timeout($timeout)
            ->withHeaders($allHeaders);
    }
}
