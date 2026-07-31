<?php

use App\Enums\IntegrationProvider;
use App\Models\Integration;
use App\Settings\GeneralSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    // 命令仅 local 可用；测试默认 testing 环境，临时按 local 探测。
    app()->detectEnvironment(fn (): string => 'local');
    $this->createUserWithInstance();
});

test('seed 命令同一应用复用同一条 mock 集成', function () {
    /** @var GeneralSettings $app */
    $app = $this->instance;

    $this->artisan('integration:seed-mock')->assertSuccessful();
    $this->artisan('integration:seed-mock')->assertSuccessful();

    expect(Integration::query()

        ->where('provider', IntegrationProvider::MockBusinessSystem)
        ->count())->toBe(1);
});
