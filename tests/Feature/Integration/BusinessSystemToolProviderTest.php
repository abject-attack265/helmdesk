<?php

use App\Data\Integration\IntegrationToolContext;
use App\Data\Integration\IntegrationToolSourceRuntimeData;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationTransport;
use App\Models\Integration;
use App\Services\Integration\Providers\BusinessSystemRequestException;
use App\Services\Integration\Providers\BusinessSystemToolProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use NeuronAI\Tools\Tool;

uses(RefreshDatabase::class);

/**
 * 构造一个 business_system 运行时来源（携带声明式工具定义）。
 *
 * @param  list<array{name: string, description: ?string, input_schema: ?array<string, mixed>}>  $tools
 * @param  list<string>  $toolNames
 */
function businessSource(array $tools, array $toolNames = [], string $baseUrl = 'https://biz.example.com'): IntegrationToolSourceRuntimeData
{
    return new IntegrationToolSourceRuntimeData(
        id: 'srv-1',
        slug: 'biz',
        name: '业务系统',
        provider: IntegrationProvider::BusinessSystem,
        transport: IntegrationTransport::Http,
        endpoint_url: $baseUrl,
        credentials: ['auth_header_name' => 'X-Secret', 'auth_header_value' => 'shhh'],
        headers: [],
        timeout_seconds: 15,
        tool_names: $toolNames,
        tools: $tools,
    );
}

test('listToolDefinitions GET manifest 并解析出工具清单', function () {
    Http::fake([
        'https://biz.example.com/helmdesk/tools' => Http::response([
            'tools' => [
                [
                    'name' => 'query_order',
                    'description' => '查订单',
                    'input_schema' => ['type' => 'object', 'properties' => ['order_no' => ['type' => 'string']], 'required' => []],
                ],
                ['name' => 'query_customer', 'description' => '查客户'],
            ],
        ]),
    ]);

    $integration = Integration::factory()->create([
        'provider' => IntegrationProvider::BusinessSystem,
        'transport' => IntegrationTransport::Http,
        'endpoint_url' => 'https://biz.example.com',
        'credentials' => ['auth_header_name' => 'X-Secret', 'auth_header_value' => 'shhh'],
        'timeout_seconds' => 15,
    ]);

    $definitions = app(BusinessSystemToolProvider::class)->listToolDefinitions($integration);

    expect($definitions)->toHaveCount(2)
        ->and($definitions[0]['name'])->toBe('query_order')
        ->and($definitions[0]['input_schema'])->toBeArray()
        ->and($definitions[1]['name'])->toBe('query_customer');

    Http::assertSent(fn ($request) => $request->hasHeader('X-Secret', 'shhh'));
});

test('listToolDefinitions 在工具清单格式错误时抛出异常', function (array $tools) {
    Http::fake([
        'https://biz.example.com/helmdesk/tools' => Http::response([
            'tools' => $tools,
        ]),
    ]);

    $integration = Integration::factory()->create([
        'provider' => IntegrationProvider::BusinessSystem,
        'transport' => IntegrationTransport::Http,
        'endpoint_url' => 'https://biz.example.com',
    ]);

    expect(fn () => app(BusinessSystemToolProvider::class)->listToolDefinitions($integration))
        ->toThrow(BusinessSystemRequestException::class);
})->with([
    '工具名称为空' => [[['name' => '']]],
    '工具名称重复' => [[['name' => 'query_order'], ['name' => 'query_order']]],
    '工具不是对象' => [['query_order']],
]);

test('listToolDefinitions 非 2xx 抛出可写回的失败异常', function () {
    Http::fake([
        'https://biz.example.com/helmdesk/tools' => Http::response(['error' => 'boom'], 500),
    ]);

    $integration = Integration::factory()->create([
        'provider' => IntegrationProvider::BusinessSystem,
        'transport' => IntegrationTransport::Http,
        'endpoint_url' => 'https://biz.example.com',
    ]);

    expect(fn () => app(BusinessSystemToolProvider::class)->listToolDefinitions($integration))
        ->toThrow(BusinessSystemRequestException::class);
});

test('buildRuntimeTools 把 input_schema 转成 NeuronAI 工具并保留必填/枚举', function () {
    $tools = app(BusinessSystemToolProvider::class)->buildRuntimeTools(businessSource([
        [
            'name' => 'query_order',
            'description' => '查订单',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'order_no' => ['type' => 'string', 'description' => '订单号'],
                    'channel' => ['type' => 'string', 'enum' => ['web', 'app']],
                    'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
                'required' => ['order_no'],
            ],
        ],
    ]));

    expect($tools)->toHaveCount(1);
    /** @var Tool $tool */
    $tool = $tools[0];
    expect($tool)->toBeInstanceOf(Tool::class)
        ->and($tool->getName())->toBe('query_order')
        ->and($tool->getProperties())->toHaveCount(3)
        ->and($tool->getRequiredProperties())->toBe(['order_no']);
});

test('buildRuntimeTools 出的 Tool 回调命中 invoke 端点并返回 content（锁死动态 callable 约定）', function () {
    Http::fake([
        'https://biz.example.com/helmdesk/tools/query_order/invoke' => Http::response([
            'content' => '订单 SO-1：已发货',
            'data' => ['order_no' => 'SO-1'],
        ]),
    ]);

    $tools = app(BusinessSystemToolProvider::class)->buildRuntimeTools(
        businessSource([
            [
                'name' => 'query_order',
                'description' => '查订单',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => ['order_no' => ['type' => 'string']],
                    'required' => ['order_no'],
                ],
            ],
        ]),
        new IntegrationToolContext(contact_external_id: 'ext-42', conversation_id: 'conv-1', email: 'vip@example.com'),
    );

    /** @var Tool $tool */
    $tool = $tools[0];

    // 模拟 LLM 填参后由 NeuronAI 运行时触发执行：setInputs + execute()。
    $tool->setInputs(['order_no' => 'SO-1']);
    $tool->execute();

    expect($tool->getResult())->toBe('订单 SO-1：已发货');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://biz.example.com/helmdesk/tools/query_order/invoke'
            && (array) $request['arguments'] === ['order_no' => 'SO-1']
            && $request['context'] === ['contact_external_id' => 'ext-42', 'email' => 'vip@example.com', 'conversation_id' => 'conv-1']
            && $request->hasHeader('X-Secret', 'shhh');
    });
});

test('invoke 回调在传入非空上下文时把联系人 external_id / 邮箱 / 会话 id 带进 context', function () {
    Http::fake([
        'https://biz.example.com/helmdesk/tools/query_order/invoke' => Http::response(['content' => 'ok']),
    ]);

    $tools = app(BusinessSystemToolProvider::class)->buildRuntimeTools(
        businessSource([
            ['name' => 'query_order', 'description' => null, 'input_schema' => null],
        ]),
        new IntegrationToolContext(contact_external_id: 'cust-9', conversation_id: 'conv-9', email: 'cust9@example.com'),
    );

    $tools[0]->setInputs([]);
    $tools[0]->execute();

    Http::assertSent(fn ($request) => $request['context'] === [
        'contact_external_id' => 'cust-9',
        'email' => 'cust9@example.com',
        'conversation_id' => 'conv-9',
    ]);
});

test('invoke 回调在 context 含邮箱但无匹配 external_id 时仍把邮箱带进 context', function () {
    Http::fake([
        'https://biz.example.com/helmdesk/tools/query_order/invoke' => Http::response(['content' => 'ok']),
    ]);

    // 联系人没有本集成 namespace 的 external_id，只有邮箱：业务系统应能退而按邮箱识别。
    $tools = app(BusinessSystemToolProvider::class)->buildRuntimeTools(
        businessSource([
            ['name' => 'query_order', 'description' => null, 'input_schema' => null],
        ]),
        new IntegrationToolContext(conversation_id: 'conv-x', email: 'only-email@example.com'),
    );

    $tools[0]->setInputs([]);
    $tools[0]->execute();

    Http::assertSent(fn ($request) => $request['context'] === [
        'contact_external_id' => null,
        'email' => 'only-email@example.com',
        'conversation_id' => 'conv-x',
    ]);
});

test('invoke 回调在 context 为 none()/null 时下发的身份字段（含 email）均为 null', function () {
    Http::fake([
        'https://biz.example.com/helmdesk/tools/query_order/invoke' => Http::response(['content' => 'ok']),
    ]);

    $source = businessSource([
        ['name' => 'query_order', 'description' => null, 'input_schema' => null],
    ]);

    // none()
    $noneTools = app(BusinessSystemToolProvider::class)->buildRuntimeTools($source, IntegrationToolContext::none());
    $noneTools[0]->setInputs([]);
    $noneTools[0]->execute();

    // 不传 context（默认 null）
    $nullTools = app(BusinessSystemToolProvider::class)->buildRuntimeTools($source);
    $nullTools[0]->setInputs([]);
    $nullTools[0]->execute();

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request['context'] === [
        'contact_external_id' => null,
        'email' => null,
        'conversation_id' => null,
    ]);
});

test('invoke 端点非 2xx 时回调返回结构化错误而不抛异常', function () {
    Http::fake([
        'https://biz.example.com/helmdesk/tools/query_order/invoke' => Http::response('nope', 503),
    ]);

    $tools = app(BusinessSystemToolProvider::class)->buildRuntimeTools(businessSource([
        [
            'name' => 'query_order',
            'description' => '查订单',
            'input_schema' => ['type' => 'object', 'properties' => ['order_no' => ['type' => 'string']], 'required' => []],
        ],
    ]));

    /** @var Tool $tool */
    $tool = $tools[0];
    $tool->setInputs(['order_no' => 'SO-1']);
    $tool->execute();

    expect($tool->getResult())->toBe(json_encode(['error' => 'tool_unavailable']));
});

test('buildRuntimeTools 按 tool_names 白名单收窄', function () {
    $tools = app(BusinessSystemToolProvider::class)->buildRuntimeTools(businessSource(
        tools: [
            ['name' => 'query_order', 'description' => null, 'input_schema' => null],
            ['name' => 'query_customer', 'description' => null, 'input_schema' => null],
        ],
        toolNames: ['query_order'],
    ));

    expect($tools)->toHaveCount(1)
        ->and($tools[0]->getName())->toBe('query_order');
});

test('buildRuntimeTools 跳过指向内网/保留地址的端点', function () {
    $tools = app(BusinessSystemToolProvider::class)->buildRuntimeTools(businessSource(
        tools: [['name' => 'query_order', 'description' => null, 'input_schema' => null]],
        baseUrl: 'http://127.0.0.1:9000',
    ));

    expect($tools)->toBe([]);
});
