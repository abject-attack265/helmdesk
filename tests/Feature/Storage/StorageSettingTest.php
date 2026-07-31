<?php

use App\Models\Attachment;
use App\Models\StorageProfile;
use App\Services\Storage\StorageProfileDisk;
use App\Settings\StorageSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\WithInstance;

uses(RefreshDatabase::class, WithInstance::class);

beforeEach(function () {
    $this->user = $this->createUserWithInstance();
});

test('所有者可以查看存储设置且默认使用本地存储', function () {
    $this->actingAs($this->user)
        ->get(route('app.manage.storage.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('appSettings/storageSetting/Index')
            ->where('settings.enabled', false)
            ->where('settings.current_profile_id', null)
            ->has('profiles', 0)
            ->has('providers')
        );
});

test('所有者可以创建对象存储配置', function () {
    $this->actingAs($this->user)
        ->post(route('app.manage.storage.profiles.store'), [
            'name' => 'Cloud files',
            'provider' => 'generic',
            'region' => 'us-east-1',
            'endpoint' => 'https://s3.example.com',
            'upload_endpoint' => 'https://upload.example.com',
            'bucket' => 'files',
            'key' => 'access-key',
            'secret' => 'secret-key',
            'url' => 'https://cdn.example.com',
        ])
        ->assertRedirect(route('app.manage.storage.index'));

    $profile = StorageProfile::query()->firstOrFail();

    expect($profile->name)->toBe('Cloud files')
        ->and($profile->provider->value)->toBe('generic')
        ->and($profile->upload_endpoint)->toBe('https://upload.example.com')
        ->and($profile->access_key)->toBe('access-key')
        ->and($profile->secret_key)->toBe('secret-key');
});

test('浏览器直传使用独立的上传 endpoint', function () {
    $profile = StorageProfile::factory()->s3()->create([
        'upload_endpoint' => 'https://upload.example.com',
    ]);

    expect(StorageProfileDisk::build($profile)->getConfig()['endpoint'])
        ->toBe('https://s3.example.com')
        ->and(StorageProfileDisk::buildForUpload($profile)->getConfig()['endpoint'])
        ->toBe('https://upload.example.com');
});

test('软删除附件仍然阻止删除存储配置', function () {
    $profile = StorageProfile::factory()->s3()->create();
    $attachment = Attachment::factory()->create(['storage_profile_id' => $profile->id]);
    $attachment->delete();

    $this->actingAs($this->user)
        ->delete(route('app.manage.storage.profiles.destroy', $profile))
        ->assertRedirect()
        ->assertSessionHasErrors('profile');

    expect(StorageProfile::query()->whereKey($profile->id)->exists())->toBeTrue();
});

test('所有者可以停用对象存储并恢复本地存储', function () {
    $settings = app(StorageSettings::class);
    $settings->enabled = true;
    $settings->current_profile_id = StorageProfile::factory()->s3()->create()->id;
    $settings->save();

    $this->actingAs($this->user)
        ->put(route('app.manage.storage.update'), [
            'enabled' => false,
            'current_profile_id' => null,
        ])
        ->assertRedirect();

    $settings = app(StorageSettings::class);
    expect($settings->enabled)->toBeFalse()
        ->and($settings->current_profile_id)->toBeNull();
});
