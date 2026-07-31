<?php

use App\Enums\ContactPanelBlockKind;
use App\Enums\ContactPanelValueType;
use App\Enums\IdentityType;
use App\Enums\IntegrationProvider;
use App\Enums\IntegrationTransport;
use App\Models\Contact;
use App\Models\ContactIdentity;
use App\Models\Integration;
use App\Services\Integration\Providers\BusinessSystemToolProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * 构造一个指向 biz.example.com 的 business_system 集成（带自定义鉴权 header）。
 */
function bizPanelIntegration(): Integration
{
    return Integration::factory()->create([
        'provider' => IntegrationProvider::BusinessSystem,
        'transport' => IntegrationTransport::Http,
        'endpoint_url' => 'https://biz.example.com',
        'credentials' => ['auth_header_name' => 'X-Secret', 'auth_header_value' => 'shhh'],
        'timeout_seconds' => 15,
    ]);
}

test('buildContactPanel 把业务系统描述符映射为 ContactPanelData', function () {
    Http::fake([
        'https://biz.example.com/helmdesk/contact-panel*' => Http::response([
            'sections' => [
                [
                    'title' => '客户概况',
                    'collapsed' => false,
                    'blocks' => [
                        [
                            'kind' => 'key_value',
                            'rows' => [
                                ['label' => '等级', 'value' => 'VIP', 'value_type' => 'badge'],
                                ['label' => '注册时间', 'value' => '2024-03-08', 'value_type' => 'date'],
                            ],
                        ],
                        [
                            'kind' => 'list',
                            'title' => '最近订单',
                            'items' => [
                                ['title' => '#SO-1024', 'subtitle' => '已发货', 'meta' => ['¥299', '2026-06-10'], 'badge' => '普通'],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $integration = bizPanelIntegration();
    $contact = Contact::factory()->create([
        'primary_email' => 'vip@example.com',
    ]);
    ContactIdentity::factory()->create([
        'contact_id' => $contact->id,
        'type' => IdentityType::ExternalId,
        'namespace' => '',
        'value' => 'cust-9',
    ]);

    $panel = app(BusinessSystemToolProvider::class)->buildContactPanel($integration, $contact);

    expect($panel)->not->toBeNull()
        ->and($panel->integration_id)->toBe((string) $integration->id)
        ->and($panel->integration_name)->toBe((string) $integration->name)
        ->and($panel->provider_label)->toBe(IntegrationProvider::BusinessSystem->label())
        ->and($panel->sections)->toHaveCount(1)
        ->and($panel->sections[0]->title)->toBe('客户概况')
        ->and($panel->sections[0]->blocks)->toHaveCount(2)
        ->and($panel->sections[0]->blocks[0]->kind)->toBe(ContactPanelBlockKind::KeyValue)
        ->and($panel->sections[0]->blocks[0]->rows[0]->value_type)->toBe(ContactPanelValueType::Badge)
        ->and($panel->sections[0]->blocks[1]->kind)->toBe(ContactPanelBlockKind::List)
        ->and($panel->sections[0]->blocks[1]->items[0]->title)->toBe('#SO-1024')
        ->and($panel->sections[0]->blocks[1]->items[0]->meta)->toBe(['¥299', '2026-06-10']);

    // 主邮箱 + external_id 作为 query 透传。
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'email=vip%40example.com')
            && str_contains($request->url(), 'external_id=cust-9')
            && $request->hasHeader('X-Secret', 'shhh');
    });
});

test('buildContactPanel 非法 kind / value_type 条目被跳过', function () {
    Http::fake([
        'https://biz.example.com/helmdesk/contact-panel*' => Http::response([
            'sections' => [
                [
                    'title' => '混入非法块',
                    'blocks' => [
                        ['kind' => 'unknown_kind', 'rows' => [['label' => 'x', 'value' => 'y', 'value_type' => 'text']]],
                        [
                            'kind' => 'key_value',
                            'rows' => [
                                ['label' => '合法', 'value' => 'ok', 'value_type' => 'text'],
                                ['label' => '非法值类型', 'value' => 'bad', 'value_type' => 'not_a_type'],
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $integration = bizPanelIntegration();
    $contact = Contact::factory()->create([]);

    $panel = app(BusinessSystemToolProvider::class)->buildContactPanel($integration, $contact);

    expect($panel)->not->toBeNull()
        ->and($panel->sections)->toHaveCount(1)
        // 非法 kind 的块被跳过，只剩一个 key_value 块。
        ->and($panel->sections[0]->blocks)->toHaveCount(1)
        ->and($panel->sections[0]->blocks[0]->kind)->toBe(ContactPanelBlockKind::KeyValue)
        // 非法 value_type 的行被跳过，只剩合法行。
        ->and($panel->sections[0]->blocks[0]->rows)->toHaveCount(1)
        ->and($panel->sections[0]->blocks[0]->rows[0]->label)->toBe('合法');
});

test('buildContactPanel 空 sections 返回 null', function () {
    Http::fake([
        'https://biz.example.com/helmdesk/contact-panel*' => Http::response(['sections' => []]),
    ]);

    $integration = bizPanelIntegration();
    $contact = Contact::factory()->create([]);

    expect(app(BusinessSystemToolProvider::class)->buildContactPanel($integration, $contact))->toBeNull();
});

test('buildContactPanel 非 2xx 返回 null 不抛异常', function () {
    Http::fake([
        'https://biz.example.com/helmdesk/contact-panel*' => Http::response(['error' => 'boom'], 404),
    ]);

    $integration = bizPanelIntegration();
    $contact = Contact::factory()->create([]);

    expect(app(BusinessSystemToolProvider::class)->buildContactPanel($integration, $contact))->toBeNull();
});

test('buildContactPanel 请求异常返回 null 不抛异常', function () {
    Http::fake(function () {
        throw new RuntimeException('connection refused');
    });

    $integration = bizPanelIntegration();
    $contact = Contact::factory()->create([]);

    expect(app(BusinessSystemToolProvider::class)->buildContactPanel($integration, $contact))->toBeNull();
});

test('buildContactPanel 指向内网/保留地址的端点返回 null', function () {
    $integration = Integration::factory()->create([
        'provider' => IntegrationProvider::BusinessSystem,
        'transport' => IntegrationTransport::Http,
        'endpoint_url' => 'http://127.0.0.1:9000',
    ]);
    $contact = Contact::factory()->create([]);

    expect(app(BusinessSystemToolProvider::class)->buildContactPanel($integration, $contact))->toBeNull();
});
