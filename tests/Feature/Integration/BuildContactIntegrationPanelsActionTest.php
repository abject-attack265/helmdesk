<?php

use App\Actions\Integration\BuildContactIntegrationPanelsAction;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationTransport;
use App\Models\Contact;
use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * 一个会返回非空面板描述符的 business_system 集成响应。
 */
function fakePanelResponse(): array
{
    return [
        'sections' => [
            [
                'title' => '客户概况',
                'blocks' => [
                    ['kind' => 'key_value', 'rows' => [['label' => '等级', 'value' => 'VIP', 'value_type' => 'badge']]],
                ],
            ],
        ],
    ];
}

test('handle 收集 business_system 面板并跳过 mcp 集成', function () {
    Http::fake([
        'https://biz.example.com/helmdesk/contact-panel*' => Http::response(fakePanelResponse()),
    ]);

    $app = createSystemSettings();

    Integration::factory()->create([
        'provider' => IntegrationProvider::BusinessSystem,
        'transport' => IntegrationTransport::Http,
        'endpoint_url' => 'https://biz.example.com',
        'name' => '业务系统',
        'sort_order' => 1,
    ]);

    // MCP 集成不实现 ProvidesContactPanel，应被跳过且不发起 HTTP。
    Integration::factory()->create([
        'provider' => IntegrationProvider::Mcp,
        'endpoint_url' => 'https://mcp.example.com/mcp',
        'sort_order' => 2,
    ]);

    $contact = Contact::factory()->create([]);

    $panels = app(BuildContactIntegrationPanelsAction::class)->handle($contact);

    expect($panels)->toHaveCount(1)
        ->and($panels[0]->integration_name)->toBe('业务系统')
        ->and($panels[0]->provider_label)->toBe(IntegrationProvider::BusinessSystem->label());

    // 仅 business_system 端点被请求，MCP 不应触发 contact-panel 调用。
    Http::assertSentCount(1);
});

test('handle 收集系统内所有支持联系人面板的集成', function () {
    Http::fake([
        'https://biz.example.com/helmdesk/contact-panel*' => Http::response(fakePanelResponse()),
    ]);

    createSystemSettings();
    Integration::factory()->create([
        'provider' => IntegrationProvider::BusinessSystem,
        'transport' => IntegrationTransport::Http,
        'endpoint_url' => 'https://biz.example.com',
    ]);

    $contact = Contact::factory()->create([]);

    $panels = app(BuildContactIntegrationPanelsAction::class)->handle($contact);

    expect($panels)->toHaveCount(1)
        ->and($panels[0]->integration_name)->not->toBeEmpty();
    Http::assertSentCount(1);
});

test('handle 返回 null 的集成不计入面板', function () {
    Http::fake([
        'https://biz.example.com/helmdesk/contact-panel*' => Http::response(['sections' => []]),
    ]);

    $app = createSystemSettings();
    Integration::factory()->create([
        'provider' => IntegrationProvider::BusinessSystem,
        'transport' => IntegrationTransport::Http,
        'endpoint_url' => 'https://biz.example.com',
    ]);

    $contact = Contact::factory()->create([]);

    expect(app(BuildContactIntegrationPanelsAction::class)->handle($contact))->toBe([]);
});
