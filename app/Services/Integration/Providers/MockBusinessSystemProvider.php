<?php

namespace App\Services\Integration\Providers;

use App\Actions\Integration\Mock\MockBusinessSystem;
use App\Contracts\Integration\IntegrationToolProvider;
use App\Contracts\Integration\ProvidesContactPanel;
use App\Data\Integration\IntegrationToolContext;
use App\Data\Integration\IntegrationToolSourceRuntimeData;
use App\Data\Integration\Panel\ContactPanelData;
use App\Enums\IntegrationProvider;
use App\Models\Contact;
use App\Models\Integration;
use App\Services\Integration\Providers\Concerns\BuildsToolFromSchema;
use NeuronAI\Tools\Tool;

/**
 * 演示用的进程内「业务系统」provider：与 BusinessSystemToolProvider 同构契约，但全程不发 HTTP、不过 SSRF guard。
 *
 * 工具清单、工具执行和联系人面板的假数据统一取自 MockBusinessSystem，运行时回调直接在进程内
 * 求值返回 content。仅由 `integration:seed-mock` 命令接入（provider=mock_business_system），用户不能手动创建，
 * 因此无需端点、凭据与连通性探测——专为单 worker 的 `php artisan serve` / `make` 等任意跑法即时可用而设。
 */
class MockBusinessSystemProvider implements IntegrationToolProvider, ProvidesContactPanel
{
    use BuildsToolFromSchema;

    /**
     * 本 provider 对应 IntegrationProvider::MockBusinessSystem。
     */
    public function provider(): IntegrationProvider
    {
        return IntegrationProvider::MockBusinessSystem;
    }

    /**
     * 进程内实现恒连通：不探测任何端点，直接返回 success。
     *
     * @return array{success: bool, code: string, message: string, supported: bool, warnings: array<int, string>}
     */
    public function checkConnection(Integration $integration): array
    {
        return [
            'success' => true,
            'code' => 'check.succeeded',
            'message' => __('integration.runtime.check.succeeded'),
            'supported' => true,
            'warnings' => [],
        ];
    }

    /**
     * 返回写死的工具清单（query_order / query_customer），供同步写回 integration_tools。
     *
     * @return list<array{name: string, description: ?string, input_schema: ?array<string, mixed>, annotations: ?array<string, mixed>}>
     */
    public function listToolDefinitions(Integration $integration): array
    {
        $definitions = [];
        foreach (MockBusinessSystem::toolDefinitions() as $tool) {
            $definitions[] = [
                'name' => $tool['name'],
                'description' => $tool['description'],
                'input_schema' => $tool['input_schema'],
                'annotations' => null,
            ];
        }

        return $definitions;
    }

    /**
     * 把 source 携带的每个工具定义装配成 NeuronAI Tool：input_schema → ToolProperty 列表，
     * callable 直接调用 MockBusinessSystem::invokeTool() 取 content（不发 HTTP、不过 SSRF guard）。
     *
     * 尊重 source.tool_names 白名单（空 = 全部）；$context 进程内 mock 不使用（恒返回写死数据）。
     *
     * @return list<Tool>
     */
    public function buildRuntimeTools(IntegrationToolSourceRuntimeData $source, ?IntegrationToolContext $context = null): array
    {
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

            $tools[] = $this->buildToolFromSchema($name, $definition, $this->buildInvoker($name));
        }

        return $tools;
    }

    /**
     * 为给定集成 + 联系人构建业务数据面板：直接用写死 sections 映射为 ContactPanelData，不发 HTTP。
     */
    public function buildContactPanel(Integration $integration, Contact $contact): ?ContactPanelData
    {
        return ContactPanelData::tryFromBusinessSystemPayload(
            ['sections' => MockBusinessSystem::contactPanelSections()],
            (string) $integration->id,
            (string) $integration->name,
            $integration->provider->label(),
        );
    }

    /**
     * 构造调用 MockBusinessSystem::invokeTool() 的进程内工具执行回调。
     *
     * NeuronAI Tool::execute() 以「属性名 => LLM 入参值」关联数组解包调用本回调，故 $args 即该映射。
     */
    private function buildInvoker(string $name): callable
    {
        return static function (...$args) use ($name): string {
            return MockBusinessSystem::invokeTool($name, $args)['content'];
        };
    }
}
