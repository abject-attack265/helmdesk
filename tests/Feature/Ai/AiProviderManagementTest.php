<?php

use App\Models\AiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
});

test('Token Plan 千问供应商使用独立端点', function () {
    $this->actingAs($this->user)
        ->post(route('app.manage.ai-providers.store'), [
            'brand' => 'qwen-token-plan',
            'name' => '千问 Token Plan',
            'configuration' => ['key' => 'sk-token-plan'],
        ])
        ->assertRedirect(route('app.manage.ai-providers.index'));

    $provider = AiProvider::query()->where('name', '千问 Token Plan')->firstOrFail();

    expect($provider->brand)->toBe('qwen-token-plan')
        ->and($provider->credentials['key'])->toBe('sk-token-plan')
        ->and($provider->credentials['base_uri'])
        ->toBe('https://token-plan.cn-beijing.maas.aliyuncs.com/compatible-mode/v1');
});
