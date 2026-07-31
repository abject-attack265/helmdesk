<?php

namespace Tests;

use App\Models\Membership;
use App\Models\User;
use App\Settings\GeneralSettings;

trait WithInstance
{
    public ?GeneralSettings $instance = null;

    /**
     * 创建一个用户并让其成为新系统的管理员。
     *
     * @param  array<string, mixed>  $userAttributes
     * @param  array<string, mixed>  $appAttributes
     */
    protected function createUserWithInstance(array $userAttributes = [], array $appAttributes = []): User
    {
        $user = User::factory()->create($userAttributes);
        $this->instance = createSystemSettings(array_merge([
            'owner_id' => $user->id,
        ], $appAttributes));
        Membership::query()->create(['user_id' => $user->id]);

        return $user;
    }

    protected function attachInstance(User $user, ?GeneralSettings $instance = null): GeneralSettings
    {
        $this->instance = $instance ?? createSystemSettings();

        Membership::query()->updateOrCreate(
            ['user_id' => $user->id],
            [],
        );

        return $this->instance;
    }
}
