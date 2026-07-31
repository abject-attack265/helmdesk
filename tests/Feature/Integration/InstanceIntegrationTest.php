<?php

use App\Actions\Integration\SyncIntegrationToolsAction;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncStatus;
use App\Enums\IntegrationTransport;
use App\Jobs\Integration\SyncIntegrationToolsJob;
use App\Models\Integration;
use App\Models\IntegrationTool;
use App\Models\User;
use App\Services\Mcp\McpRuntimeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
});

/**
 * 绑定一个返回固定成功结果并记录调用入参的假 MCP 运行时客户端。
 * tools 数组通过参数注入以覆盖增量同步用例。
 *
 * @param  array<int, array<string, mixed>>  $tools
 * @return object{checkCalled: bool, listCalled: bool, checkedIntegration: ?Integration, checkedCredentials: ?array}
 */
function fakeMcpBridge(array $tools = []): object
{
    $capture = new class
    {
        public bool $checkCalled = false;

        public bool $listCalled = false;

        public ?Integration $checkedIntegration = null;

        /** @var array<string, mixed>|null */
        public ?array $checkedCredentials = null;
    };

    $client = Mockery::mock(McpRuntimeClient::class);
    $client->shouldReceive('checkServerConnection')
        ->andReturnUsing(function (Integration $integration, array $credentials, ?array $headers = null) use ($capture) {
            $capture->checkCalled = true;
            $capture->checkedIntegration = $integration;
            $capture->checkedCredentials = $credentials;

            return [
                'success' => true,
                'code' => 'check.succeeded',
                'message' => __('integration.runtime.check.succeeded'),
                'supported' => true,
                'warnings' => [],
            ];
        });
    $client->shouldReceive('listServerTools')
        ->andReturnUsing(function (Integration $integration, array $credentials, ?array $headers = null) use ($tools, $capture) {
            $capture->listCalled = true;

            return [
                'success' => true,
                'code' => 'list_tools.succeeded',
                'message' => __('integration.runtime.list_tools.succeeded'),
                'supported' => true,
                'warnings' => [],
                'tools' => $tools,
            ];
        });
    app()->instance(McpRuntimeClient::class, $client);

    return $capture;
}

/**
 * 绑定一个 check 返回失败的假 MCP 运行时客户端。
 */
function fakeMcpBridgeCheckFailure(string $code, string $message): void
{
    $client = Mockery::mock(McpRuntimeClient::class);
    $client->shouldReceive('checkServerConnection')->andReturnUsing(fn (): array => [
        'success' => false,
        'code' => $code,
        'message' => __('integration.runtime.check.failed', ['error' => $message]),
        'supported' => true,
        'warnings' => [],
    ]);
    $client->shouldReceive('listServerTools')->andReturnUsing(fn (): array => [
        'success' => true,
        'code' => 'list_tools.succeeded',
        'message' => __('integration.runtime.list_tools.succeeded'),
        'supported' => true,
        'warnings' => [],
        'tools' => [],
    ]);
    app()->instance(McpRuntimeClient::class, $client);
}

test('非所有者应用成员不能访问集成设置', function () {
    $admin = User::factory()->create();
    attachMember($admin);

    $this->actingAs($admin)
        ->get(route('app.manage.integrations.index', []))
        ->assertForbidden();
});

test('创建页 provider 选项不含进程内 mock，仅含 mcp / business_system', function () {
    $response = $this->actingAs($this->user)
        ->get(route('app.manage.integrations.create', []))
        ->assertOk();

    $options = $response->viewData('page')['props']['provider_options'];
    $values = collect($options)->pluck('value')->all();

    expect($values)->toBe(['mcp', 'business_system']);
});

test('创建表单拒绝手动提交进程内 mock provider', function () {
    fakeMcpBridge();

    $this->actingAs($this->user)
        ->from(route('app.manage.integrations.index', []))
        ->post(route('app.manage.integrations.store', []), [
            'name' => 'Sneaky Mock',
            'endpoint_url' => 'https://erp.example.com',
            'provider' => 'mock_business_system',
        ])
        ->assertSessionHasErrors(['provider']);

    expect(Integration::query()->count())->toBe(0);
});

test('所有者可以打开集成编辑页，认证凭据明文回显', function () {
    $integration = Integration::factory()
        ->withBearerToken('original-token')
        ->create();

    $this->actingAs($this->user)
        ->get(route('app.manage.integrations.edit', [
            'server' => $integration->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('appSettings/integrations/Edit')
            ->where('server.slug', $integration->slug)
            ->where('server.auth_header_name', 'Authorization')
            ->where('server.auth_header_value', 'Bearer original-token'));
});

test('创建集成只保存配置，不触发工具同步', function () {
    $capture = fakeMcpBridge();

    $this->actingAs($this->user)
        ->post(route('app.manage.integrations.store', []), [
            'name' => 'Shopify MCP',
            'endpoint_url' => 'https://mcp.example.com/v1',
            'provider' => 'mcp',
            'auth_header_name' => 'Authorization',
            'auth_header_value' => 'Bearer secret-token',
            'timeout_seconds' => 30,
        ])
        ->assertRedirect();

    $integration = Integration::query()->firstOrFail();

    expect($integration->name)->toBe('Shopify MCP');
    expect($integration->credentials['auth_header_name'])->toBe('Authorization');
    expect($integration->credentials['auth_header_value'])->toBe('Bearer secret-token');
    expect($integration->last_sync_status)->toBe(IntegrationSyncStatus::Pending);
    expect($integration->tools()->count())->toBe(0);

    expect($capture->checkCalled)->toBeFalse()
        ->and($capture->listCalled)->toBeFalse();
});

test('创建 MCP 集成派生 streamable_http 传输协议', function () {
    fakeMcpBridge();

    $this->actingAs($this->user)
        ->post(route('app.manage.integrations.store', []), [
            'name' => 'MCP Integration',
            'endpoint_url' => 'https://mcp.example.com/v1',
            'provider' => 'mcp',
        ])
        ->assertRedirect();

    $integration = Integration::query()->firstOrFail();

    expect($integration->provider)->toBe(IntegrationProvider::Mcp)
        ->and($integration->transport)->toBe(IntegrationTransport::StreamableHttp);
});

test('创建业务系统集成派生 http 传输协议', function () {
    fakeMcpBridge();

    $this->actingAs($this->user)
        ->post(route('app.manage.integrations.store', []), [
            'name' => 'Business System',
            'endpoint_url' => 'https://erp.example.com',
            'provider' => 'business_system',
        ])
        ->assertRedirect();

    $integration = Integration::query()->firstOrFail();

    expect($integration->provider)->toBe(IntegrationProvider::BusinessSystem)
        ->and($integration->transport)->toBe(IntegrationTransport::Http);
});

test('创建表单拒绝非法 provider', function () {
    fakeMcpBridge();

    $this->actingAs($this->user)
        ->from(route('app.manage.integrations.index', []))
        ->post(route('app.manage.integrations.store', []), [
            'name' => 'Bad Provider',
            'endpoint_url' => 'https://mcp.example.com/v1',
            'provider' => 'not_a_provider',
        ])
        ->assertSessionHasErrors(['provider']);

    expect(Integration::query()->count())->toBe(0);
});

test('创建表单校验 endpoint_url 必填且必须为 URL', function () {
    fakeMcpBridge();

    $this->actingAs($this->user)
        ->from(route('app.manage.integrations.index', []))
        ->post(route('app.manage.integrations.store', []), [
            'name' => 'Bad',
            'endpoint_url' => 'not-a-url',
            'provider' => 'mcp',
        ])
        ->assertRedirect(route('app.manage.integrations.index', []))
        ->assertSessionHasErrors(['endpoint_url']);
});

test('创建表单拒绝指向内网/保留地址的 endpoint', function () {
    fakeMcpBridge();

    foreach (['http://127.0.0.1/mcp', 'http://169.254.169.254/latest/meta-data', 'http://localhost:6379/mcp'] as $unsafe) {
        $this->actingAs($this->user)
            ->from(route('app.manage.integrations.index', []))
            ->post(route('app.manage.integrations.store', []), [
                'name' => 'SSRF',
                'endpoint_url' => $unsafe,
                'provider' => 'mcp',
            ])
            ->assertSessionHasErrors(['endpoint_url']);
    }

    expect(Integration::query()->count())->toBe(0);
});

test('认证 header name 与 value 必须成对出现', function () {
    fakeMcpBridge();

    // 模拟真实前端：表单字段始终随提交一并送出（空字符串），中间件后端再转 null。
    // 用户填了 name 没填 value 的场景下，规则应识别为半配置并报错。
    $this->actingAs($this->user)
        ->from(route('app.manage.integrations.index', []))
        ->post(route('app.manage.integrations.store', []), [
            'name' => 'Half Auth',
            'endpoint_url' => 'https://mcp.example.com/v1',
            'provider' => 'mcp',
            'auth_header_name' => 'Authorization',
            'auth_header_value' => '',
        ])
        ->assertRedirect(route('app.manage.integrations.index', []))
        ->assertSessionHasErrors(['auth_header_value']);
});

test('支持自定义认证 header 名（如 X-Api-Key）', function () {
    fakeMcpBridge();

    $this->actingAs($this->user)
        ->post(route('app.manage.integrations.store', []), [
            'name' => 'Stripe-like MCP',
            'endpoint_url' => 'https://mcp.example.com/api',
            'provider' => 'mcp',
            'auth_header_name' => 'X-Api-Key',
            'auth_header_value' => 'sk_live_xxx',
        ])
        ->assertRedirect();

    $integration = Integration::query()->firstOrFail();
    expect($integration->credentials['auth_header_name'])->toBe('X-Api-Key');
    expect($integration->credentials['auth_header_value'])->toBe('sk_live_xxx');
});

test('更新表单缺少认证协议字段时失败', function () {
    fakeMcpBridge();

    $integration = Integration::factory()
        ->withBearerToken('original-token')
        ->create();

    $this->actingAs($this->user)
        ->from(route('app.manage.integrations.edit', [
            'server' => $integration->slug,
        ]))
        ->put(route('app.manage.integrations.update', [
            'server' => $integration->slug,
        ]), [
            'name' => 'Renamed',
            'endpoint_url' => 'https://mcp.example.com/v2',
            'timeout_seconds' => 45,
        ])
        ->assertRedirect(route('app.manage.integrations.edit', [
            'server' => $integration->slug,
        ]))
        ->assertSessionHasErrors(['auth_header_name', 'auth_header_value']);

    expect($integration->fresh()->name)->not->toBe('Renamed');
});

test('更新表单原样提交回显的凭据时保持不变', function () {
    $capture = fakeMcpBridge();

    $integration = Integration::factory()
        ->withBearerToken('original-token')
        ->create();

    $this->actingAs($this->user)
        ->put(route('app.manage.integrations.update', [
            'server' => $integration->slug,
        ]), [
            'name' => 'Renamed',
            'endpoint_url' => 'https://mcp.example.com/v2',
            'auth_header_name' => 'Authorization',
            'auth_header_value' => 'Bearer original-token',
            'timeout_seconds' => 45,
        ])
        ->assertRedirect(route('app.manage.integrations.index', []));

    $integration->refresh();
    expect($integration->name)->toBe('Renamed');
    expect($integration->endpoint_url)->toBe('https://mcp.example.com/v2');
    expect($integration->credentials['auth_header_name'])->toBe('Authorization');
    expect($integration->credentials['auth_header_value'])->toBe('Bearer original-token');
    expect($integration->timeout_seconds)->toBe(45);

    expect($capture->checkCalled)->toBeFalse()
        ->and($capture->listCalled)->toBeFalse();
});

test('认证字段提交 null 时清空凭据', function () {
    fakeMcpBridge();

    $integration = Integration::factory()
        ->withBearerToken('original-token')
        ->create();

    $this->actingAs($this->user)
        ->put(route('app.manage.integrations.update', [
            'server' => $integration->slug,
        ]), [
            'name' => $integration->name,
            'endpoint_url' => $integration->endpoint_url,
            'auth_header_name' => null,
            'auth_header_value' => null,
        ])
        ->assertRedirect();

    expect($integration->fresh()->credentials)->toBeNull();
});

test('更新表单认证字段为空字符串时同样清空凭据', function () {
    fakeMcpBridge();

    // 真实前端会把清空后的字段以空字符串送出。
    $integration = Integration::factory()
        ->withBearerToken('original-token')
        ->create();

    $this->actingAs($this->user)
        ->put(route('app.manage.integrations.update', [
            'server' => $integration->slug,
        ]), [
            'name' => $integration->name,
            'endpoint_url' => $integration->endpoint_url,
            'auth_header_name' => '',
            'auth_header_value' => '',
        ])
        ->assertRedirect(route('app.manage.integrations.index', []));

    expect($integration->fresh()->credentials)->toBeNull();
});

test('Check 端点成功时返回 JSON 结果', function () {
    fakeMcpBridge();

    $integration = Integration::factory()->create();

    $this->actingAs($this->user)
        ->post(route('app.manage.integrations.check', [
            'server' => $integration->slug,
        ]))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => '连接正常。',
        ]);
});

test('Check 端点使用当前表单配置而不是已保存配置', function () {
    $capture = fakeMcpBridge();

    $integration = Integration::factory()
        ->withBearerToken('old-token')
        ->create([
            'endpoint_url' => 'https://old.example.com/mcp',
            'timeout_seconds' => 30,
        ]);

    $this->actingAs($this->user)
        ->post(route('app.manage.integrations.check', [
            'server' => $integration->slug,
        ]), [
            'name' => $integration->name,
            'endpoint_url' => 'https://new.example.com/mcp',
            'auth_header_name' => 'Authorization',
            'auth_header_value' => 'Bearer new-token',
            'timeout_seconds' => 45,
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => '连接正常。',
        ]);

    expect($integration->fresh()->endpoint_url)->toBe('https://old.example.com/mcp');

    expect($capture->checkedIntegration->endpoint_url)->toBe('https://new.example.com/mcp')
        ->and($capture->checkedCredentials['auth_header_value'])->toBe('Bearer new-token')
        ->and($capture->checkedIntegration->timeout_seconds)->toBe(45);
});

test('Check 端点支持测试尚未保存的新配置', function () {
    $capture = fakeMcpBridge();

    $this->actingAs($this->user)
        ->post(route('app.manage.integrations.check-unsaved', [
        ]), [
            'name' => 'Unsaved MCP',
            'endpoint_url' => 'https://unsaved.example.com/mcp',
            'provider' => 'mcp',
            'auth_header_name' => 'Authorization',
            'auth_header_value' => 'Bearer unsaved-token',
            'timeout_seconds' => 20,
        ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => '连接正常。',
        ]);

    expect(Integration::query()->count())->toBe(0);

    expect($capture->checkedIntegration->name)->toBe('Unsaved MCP')
        ->and($capture->checkedIntegration->endpoint_url)->toBe('https://unsaved.example.com/mcp')
        ->and($capture->checkedCredentials['auth_header_value'])->toBe('Bearer unsaved-token')
        ->and($capture->checkedIntegration->timeout_seconds)->toBe(20);
});

test('Check 失败时返回 JSON 失败原因', function () {
    fakeMcpBridgeCheckFailure('check.failed', 'connection refused');

    $integration = Integration::factory()->create();

    $response = $this->actingAs($this->user)
        ->from(route('app.manage.integrations.index', []))
        ->post(route('app.manage.integrations.check', [
            'server' => $integration->slug,
        ]))
        ->assertOk()
        ->assertJsonPath('success', false);

    expect($response->json('message'))
        ->toBeString()
        ->toContain('connection refused');
});

test('Check 端点对业务系统集成探测 manifest 端点（不走 MCP）', function () {
    Http::fake([
        'https://erp.example.com/helmdesk/tools' => Http::response(['tools' => []]),
    ]);

    $integration = Integration::factory()->create([
        'provider' => IntegrationProvider::BusinessSystem,
        'transport' => IntegrationTransport::Http,
        'endpoint_url' => 'https://erp.example.com',
    ]);

    $this->actingAs($this->user)
        ->post(route('app.manage.integrations.check', [
            'server' => $integration->slug,
        ]))
        ->assertOk()
        ->assertJson(['success' => true]);
});

test('Check 端点业务系统集成端点不可达时返回失败', function () {
    Http::fake([
        'https://erp.example.com/helmdesk/tools' => Http::response('err', 500),
    ]);

    $integration = Integration::factory()->create([
        'provider' => IntegrationProvider::BusinessSystem,
        'transport' => IntegrationTransport::Http,
        'endpoint_url' => 'https://erp.example.com',
    ]);

    $this->actingAs($this->user)
        ->post(route('app.manage.integrations.check', [
            'server' => $integration->slug,
        ]))
        ->assertOk()
        ->assertJson(['success' => false]);
});

test('Sync 新增并下线工具', function () {
    fakeMcpBridge([
        ['name' => 'new_tool', 'description' => 'Brand new tool'],
    ]);

    $integration = Integration::factory()->create();
    IntegrationTool::factory()->for($integration, 'server')->create([
        'name' => 'old_tool',
        'is_enabled' => true,
    ]);

    $result = app(SyncIntegrationToolsAction::class)->syncServer($integration);

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toBe(__('integration.messages.sync_succeeded', [
            'total' => 1,
            'added' => 1,
            'removed' => 1,
        ]))
        ->and($result['total'])->toBe(1)
        ->and($result['added'])->toBe(1)
        ->and($result['removed'])->toBe(1);

    $newTool = $integration->tools()->where('name', 'new_tool')->firstOrFail();
    expect($newTool->is_enabled)->toBeTrue();
    expect($newTool->removed_at)->toBeNull();

    $oldTool = $integration->tools()->where('name', 'old_tool')->firstOrFail();
    expect($oldTool->is_enabled)->toBeFalse();
    expect($oldTool->removed_at)->not->toBeNull();

    expect($integration->fresh()->last_sync_status)->toBe(IntegrationSyncStatus::Success);
});

test('同步全部会把应用内集成逐个入队', function () {
    fakeMcpBridge();
    Queue::fake();

    $first = Integration::factory()->create();
    $second = Integration::factory()->create();
    $third = Integration::factory()->create();

    $this->actingAs($this->user)
        ->post(route('app.manage.integrations.sync-all', [
        ]))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'queued' => 3,
        ]);

    expect($first->fresh()->last_sync_status)->toBe(IntegrationSyncStatus::Syncing)
        ->and($second->fresh()->last_sync_status)->toBe(IntegrationSyncStatus::Syncing)
        ->and($third->fresh()->last_sync_status)->toBe(IntegrationSyncStatus::Syncing);

    Queue::assertPushed(SyncIntegrationToolsJob::class, 3);
    Queue::assertPushed(
        SyncIntegrationToolsJob::class,
        fn (SyncIntegrationToolsJob $job): bool => $job->integrationId === (string) $first->id,
    );
    Queue::assertPushed(
        SyncIntegrationToolsJob::class,
        fn (SyncIntegrationToolsJob $job): bool => $job->integrationId === (string) $second->id,
    );
    Queue::assertPushed(
        SyncIntegrationToolsJob::class,
        fn (SyncIntegrationToolsJob $job): bool => $job->integrationId === (string) $third->id,
    );
});

test('禁用并重新启用工具', function () {
    fakeMcpBridge();

    $integration = Integration::factory()->create();
    $tool = IntegrationTool::factory()->for($integration, 'server')->create([
        'name' => 'tool_a',
        'is_enabled' => true,
        'removed_at' => null,
    ]);

    $this->actingAs($this->user)
        ->put(route('app.manage.integrations.tools.toggle', [
            'server' => $integration->slug,
            'tool' => $tool->id,
        ]))
        ->assertRedirect();

    expect($tool->fresh()->is_enabled)->toBeFalse();

    $this->actingAs($this->user)
        ->put(route('app.manage.integrations.tools.toggle', [
            'server' => $integration->slug,
            'tool' => $tool->id,
        ]))
        ->assertRedirect();

    expect($tool->fresh()->is_enabled)->toBeTrue();
});

test('已下线工具不能再启用', function () {
    fakeMcpBridge();

    $integration = Integration::factory()->create();
    $tool = IntegrationTool::factory()->removed()->for($integration, 'server')->create([
        'name' => 'gone',
    ]);

    $this->actingAs($this->user)
        ->from(route('app.manage.integrations.index', []))
        ->withHeader('X-Inertia', 'true')
        ->put(route('app.manage.integrations.tools.toggle', [
            'server' => $integration->slug,
            'tool' => $tool->id,
        ]))
        ->assertRedirect(route('app.manage.integrations.index', []))
        ->assertSessionHasErrors(['toast']);

    expect($tool->fresh()->is_enabled)->toBeFalse();
});

test('删除集成会一并清理工具记录', function () {
    fakeMcpBridge();

    $integration = Integration::factory()->create();
    IntegrationTool::factory()->for($integration, 'server')->count(3)->create();

    $this->actingAs($this->user)
        ->delete(route('app.manage.integrations.destroy', [
            'server' => $integration->slug,
        ]))
        ->assertRedirect();

    expect(Integration::query()->find($integration->id))->toBeNull();
    expect(IntegrationTool::query()->where('integration_id', $integration->id)->count())->toBe(0);
});
