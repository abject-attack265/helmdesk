<?php

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationTransport;
use App\Models\Contact;
use App\Models\Integration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
});

test('带 business_system 集成时返回该联系人的业务数据面板', function () {
    Http::fake([
        'https://biz.example.com/helmdesk/contact-panel*' => Http::response([
            'sections' => [
                [
                    'title' => '客户概况',
                    'blocks' => [
                        ['kind' => 'key_value', 'rows' => [['label' => '等级', 'value' => 'VIP', 'value_type' => 'badge']]],
                    ],
                ],
            ],
        ]),
    ]);

    Integration::factory()->create([
        'provider' => IntegrationProvider::BusinessSystem,
        'transport' => IntegrationTransport::Http,
        'endpoint_url' => 'https://biz.example.com',
        'name' => '业务系统',
    ]);

    $contact = Contact::factory()->create([]);

    $this->actingAs($this->user)
        ->getJson('/app'.'/inbox/contacts/'.$contact->id.'/integration-panels')
        ->assertOk()
        ->assertJsonCount(1, 'panels')
        ->assertJsonPath('panels.0.integration_name', '业务系统')
        ->assertJsonPath('panels.0.sections.0.title', '客户概况')
        ->assertJsonPath('panels.0.sections.0.blocks.0.kind', 'key_value');
});

test('无集成时返回空面板列表', function () {
    $contact = Contact::factory()->create([]);

    $this->actingAs($this->user)
        ->getJson('/app'.'/inbox/contacts/'.$contact->id.'/integration-panels')
        ->assertOk()
        ->assertJsonCount(0, 'panels');
});
