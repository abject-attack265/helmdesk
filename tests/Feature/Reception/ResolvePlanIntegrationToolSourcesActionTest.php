<?php

use App\Actions\Reception\Plan\ResolvePlanIntegrationToolSourcesAction;
use App\Data\Reception\Plan\CompiledIntegrationGrantData;
use App\Models\Integration;
use App\Models\IntegrationTool;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->instance = createSystemSettings(['name' => 'Test Instance']);
});

/**
 * 构造一个集成授权快照项。
 *
 * @param  list<string>  $toolNames
 */
function planGrant(string $integrationId, array $toolNames = []): CompiledIntegrationGrantData
{
    return new CompiledIntegrationGrantData(
        integration_id: $integrationId,
        integration_name: '集成',
        tool_names: $toolNames,
    );
}

test('白名单为空时放行该集成全部已启用工具', function () {
    $integration = Integration::factory()->create([
        'slug' => 'orders-mcp',
        'name' => '订单 MCP',
        'endpoint_url' => 'https://example.com/mcp',
        'credentials' => ['auth_header_name' => 'X-Auth', 'auth_header_value' => 'secret'],
        'headers' => ['X-Trace' => 'plan-runtime'],
        'timeout_seconds' => 45,
    ]);
    IntegrationTool::factory()->for($integration, 'server')->create(['name' => 'lookup_order']);
    IntegrationTool::factory()->for($integration, 'server')->create(['name' => 'cancel_order']);

    $payload = app(ResolvePlanIntegrationToolSourcesAction::class)
        ->handle([planGrant($integration->id)]);

    expect($payload)->toHaveCount(1)
        ->and($payload[0]->id)->toBe((string) $integration->id)
        ->and($payload[0]->slug)->toBe('orders-mcp')
        ->and($payload[0]->endpoint_url)->toBe('https://example.com/mcp')
        ->and($payload[0]->timeout_seconds)->toBe(45)
        ->and($payload[0]->credentials)->toBe(['auth_header_name' => 'X-Auth', 'auth_header_value' => 'secret'])
        ->and($payload[0]->headers)->toBe(['X-Trace' => 'plan-runtime'])
        ->and($payload[0]->tool_names)->toEqualCanonicalizing(['lookup_order', 'cancel_order']);
});

test('白名单非空时收窄为白名单 ∩ 当前已启用工具', function () {
    $integration = Integration::factory()->create();
    IntegrationTool::factory()->for($integration, 'server')->create(['name' => 'lookup_order']);
    IntegrationTool::factory()->for($integration, 'server')->create(['name' => 'cancel_order']);
    IntegrationTool::factory()->for($integration, 'server')->create(['name' => 'refund_order']);

    // 白名单含一个已不存在的工具名 ghost_tool，应被交集剔除。
    $payload = app(ResolvePlanIntegrationToolSourcesAction::class)
        ->handle([planGrant($integration->id, ['lookup_order', 'refund_order', 'ghost_tool'])]);

    expect($payload)->toHaveCount(1)
        ->and($payload[0]->tool_names)->toEqualCanonicalizing(['lookup_order', 'refund_order']);
});

test('已禁用 / 已下线工具被排除（白名单为空取全部已启用）', function () {
    $integration = Integration::factory()->create();
    IntegrationTool::factory()->for($integration, 'server')->create(['name' => 'enabled', 'is_enabled' => true]);
    IntegrationTool::factory()->for($integration, 'server')->create(['name' => 'disabled', 'is_enabled' => false]);
    IntegrationTool::factory()->for($integration, 'server')->create(['name' => 'removed', 'removed_at' => now()]);

    $payload = app(ResolvePlanIntegrationToolSourcesAction::class)
        ->handle([planGrant($integration->id)]);

    expect($payload)->toHaveCount(1)
        ->and($payload[0]->tool_names)->toBe(['enabled']);
});

test('白名单全部不可用时整台跳过', function () {
    $integration = Integration::factory()->create();
    IntegrationTool::factory()->for($integration, 'server')->create(['name' => 'enabled']);

    $payload = app(ResolvePlanIntegrationToolSourcesAction::class)
        ->handle([planGrant($integration->id, ['ghost_tool'])]);

    expect($payload)->toBe([]);
});

test('上次同步失败的集成整台跳过', function () {
    $integration = Integration::factory()->syncFailed()->create();
    IntegrationTool::factory()->for($integration, 'server')->create(['name' => 'lookup']);

    $payload = app(ResolvePlanIntegrationToolSourcesAction::class)
        ->handle([planGrant($integration->id)]);

    expect($payload)->toBe([]);
});

test('空 credentials / headers 归一化为空 map', function () {
    $integration = Integration::factory()->create([
        'credentials' => [],
        'headers' => null,
    ]);
    IntegrationTool::factory()->for($integration, 'server')->create();

    $payload = app(ResolvePlanIntegrationToolSourcesAction::class)
        ->handle([planGrant($integration->id)]);

    expect($payload[0]->credentials)->toBe([])
        ->and($payload[0]->headers)->toBe([]);
});

test('空授权列表返回空数组', function () {
    $payload = app(ResolvePlanIntegrationToolSourcesAction::class)
        ->handle([]);

    expect($payload)->toBe([]);
});
