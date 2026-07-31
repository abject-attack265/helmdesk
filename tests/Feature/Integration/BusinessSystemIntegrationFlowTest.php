<?php

use App\Actions\AiChat\CollectActiveIntegrationToolSourcesAction;
use App\Actions\Integration\SyncIntegrationToolsAction;
use App\Actions\Reception\Plan\ResolvePlanIntegrationToolSourcesAction;
use App\Data\Reception\Plan\CompiledIntegrationGrantData;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncStatus;
use App\Enums\IntegrationTransport;
use App\Models\Integration;
use App\Services\Integration\IntegrationToolBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use NeuronAI\Tools\Tool;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->instance = createSystemSettings();
    $this->integration = Integration::factory()->create([
        'provider' => IntegrationProvider::BusinessSystem,
        'transport' => IntegrationTransport::Http,
        'endpoint_url' => 'https://biz.example.com',
        'credentials' => ['auth_header_name' => 'X-Secret', 'auth_header_value' => 'shhh'],
        'timeout_seconds' => 15,
    ]);
});

/**
 * 假 manifest 响应（两个工具）。
 */
function fakeBusinessManifest(): void
{
    Http::fake([
        'https://biz.example.com/helmdesk/tools' => Http::response([
            'tools' => [
                [
                    'name' => 'query_order',
                    'description' => '查订单',
                    'input_schema' => ['type' => 'object', 'properties' => ['order_no' => ['type' => 'string']], 'required' => []],
                ],
                [
                    'name' => 'query_customer',
                    'description' => '查客户',
                    'input_schema' => ['type' => 'object', 'properties' => ['email' => ['type' => 'string']], 'required' => ['email']],
                ],
            ],
        ]),
    ]);
}

test('SyncIntegrationToolsAction 对 business_system 同步落 2 行工具', function () {
    fakeBusinessManifest();

    $result = app(SyncIntegrationToolsAction::class)->syncServer($this->integration);

    expect($result['success'])->toBeTrue()
        ->and($result['total'])->toBe(2)
        ->and($result['added'])->toBe(2);

    expect($this->integration->tools()->count())->toBe(2)
        ->and($this->integration->fresh()->last_sync_status)->toBe(IntegrationSyncStatus::Success);

    $names = $this->integration->tools()->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(['query_order', 'query_customer']);
});

test('端到端：sync → 接待方案授权 → 解析出带 tools 的 source → builder 构出可用 Tool', function () {
    fakeBusinessManifest();
    app(SyncIntegrationToolsAction::class)->syncServer($this->integration);

    $grant = new CompiledIntegrationGrantData(
        integration_id: (string) $this->integration->id,
        integration_name: $this->integration->name,
        tool_names: [],
    );

    $sources = app(ResolvePlanIntegrationToolSourcesAction::class)->handle([$grant]);

    expect($sources)->toHaveCount(1)
        ->and($sources[0]->provider)->toBe(IntegrationProvider::BusinessSystem)
        ->and($sources[0]->tools)->toHaveCount(2)
        ->and($sources[0]->tool_names)->toEqualCanonicalizing(['query_order', 'query_customer']);

    $tools = app(IntegrationToolBuilder::class)->build($sources);

    expect($tools)->toHaveCount(2)
        ->and($tools[0])->toBeInstanceOf(Tool::class);

    // 装配出的工具回调可直接打到 invoke 端点。
    Http::fake([
        'https://biz.example.com/helmdesk/tools/query_order/invoke' => Http::response(['content' => 'OK']),
    ]);
    $orderTool = collect($tools)->firstOrFail(fn (Tool $t): bool => $t->getName() === 'query_order');
    $orderTool->setInputs(['order_no' => 'SO-9']);
    $orderTool->execute();
    expect($orderTool->getResult())->toBe('OK');
});

test('员工端 AI 助手收集 business_system 工具来源也带 provider 与 tools', function () {
    fakeBusinessManifest();
    app(SyncIntegrationToolsAction::class)->syncServer($this->integration->fresh());
    $this->integration->update(['last_sync_status' => IntegrationSyncStatus::Success]);

    $sources = app(CollectActiveIntegrationToolSourcesAction::class)->handle();

    expect($sources)->toHaveCount(1)
        ->and($sources[0]->provider)->toBe(IntegrationProvider::BusinessSystem)
        ->and($sources[0]->tools)->toHaveCount(2);
});
