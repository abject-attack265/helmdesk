<?php

use App\Actions\Integration\Mock\MockBusinessSystem;

/**
 * 带正确共享密钥发起 mock 请求的请求头。
 *
 * @return array<string, string>
 */
function mockAuthHeader(): array
{
    return [MockBusinessSystem::AUTH_HEADER => MockBusinessSystem::SECRET];
}

test('manifest 端点返回两个工具定义', function () {
    $this->getJson('/mock-business-system/helmdesk/tools', mockAuthHeader())
        ->assertOk()
        ->assertJsonCount(2, 'tools')
        ->assertJsonPath('tools.0.name', 'query_order')
        ->assertJsonPath('tools.1.name', 'query_customer');
});

test('invoke query_order 返回订单结论', function () {
    $this->postJson(
        '/mock-business-system/helmdesk/tools/query_order/invoke',
        ['arguments' => ['order_no' => 'SO-123'], 'context' => []],
        mockAuthHeader(),
    )
        ->assertOk()
        ->assertJsonPath('data.order_no', 'SO-123')
        ->assertJsonStructure(['content', 'data' => ['order_no', 'status', 'tracking_no']]);
});

test('invoke query_customer 返回客户结论', function () {
    $this->postJson(
        '/mock-business-system/helmdesk/tools/query_customer/invoke',
        ['arguments' => ['email' => 'a@b.com']],
        mockAuthHeader(),
    )
        ->assertOk()
        ->assertJsonPath('data.email', 'a@b.com')
        ->assertJsonStructure(['content', 'data' => ['name', 'level', 'order_count']]);
});

test('错误鉴权返回 401', function () {
    $this->getJson('/mock-business-system/helmdesk/tools', [MockBusinessSystem::AUTH_HEADER => 'wrong'])
        ->assertStatus(401);

    $this->postJson('/mock-business-system/helmdesk/tools/query_order/invoke', [], [])
        ->assertStatus(401);
});

test('未知工具名返回 404', function () {
    $this->postJson('/mock-business-system/helmdesk/tools/unknown/invoke', ['arguments' => []], mockAuthHeader())
        ->assertNotFound();
});

test('非 local/testing 环境一律 404', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->getJson('/mock-business-system/helmdesk/tools', mockAuthHeader())
        ->assertNotFound();
});

test('contact-panel 端点返回写死的业务数据描述符', function () {
    $this->getJson('/mock-business-system/helmdesk/contact-panel?email=any@example.com', mockAuthHeader())
        ->assertOk()
        ->assertJsonPath('sections.0.blocks.0.kind', 'key_value')
        ->assertJsonPath('sections.0.blocks.0.rows.0.value_type', 'badge')
        ->assertJsonPath('sections.1.blocks.0.kind', 'list')
        ->assertJsonCount(3, 'sections.1.blocks.0.items');
});

test('contact-panel 错误鉴权返回 401', function () {
    $this->getJson('/mock-business-system/helmdesk/contact-panel', [MockBusinessSystem::AUTH_HEADER => 'wrong'])
        ->assertStatus(401);
});

test('contact-panel 非 local/testing 环境一律 404', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->getJson('/mock-business-system/helmdesk/contact-panel', mockAuthHeader())
        ->assertNotFound();
});
