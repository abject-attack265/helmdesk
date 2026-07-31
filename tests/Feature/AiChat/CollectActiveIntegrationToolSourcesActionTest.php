<?php

use App\Actions\AiChat\CollectActiveIntegrationToolSourcesAction;
use App\Models\Integration;
use App\Models\IntegrationTool;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->instance = createSystemSettings(['name' => 'Test Instance']);
});

test('收集配置完整且有启用工具的集成', function () {
    $integration = Integration::factory()->synced()->create(['slug' => 'orders-mcp']);
    IntegrationTool::factory()->for($integration, 'server')->create(['name' => 'lookup_order', 'is_enabled' => true]);

    $payload = app(CollectActiveIntegrationToolSourcesAction::class)->handle();

    expect($payload)->toHaveCount(1)
        ->and($payload[0]->slug)->toBe('orders-mcp')
        ->and($payload[0]->tool_names)->toBe(['lookup_order']);
});

test('上次同步失败的集成不挂给 AI Agent', function () {
    $integration = Integration::factory()->syncFailed()->create();
    IntegrationTool::factory()->for($integration, 'server')->create(['name' => 'lookup_order', 'is_enabled' => true]);

    $payload = app(CollectActiveIntegrationToolSourcesAction::class)->handle();

    expect($payload)->toBe([]);
});
