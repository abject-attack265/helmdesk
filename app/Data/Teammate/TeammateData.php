<?php

namespace App\Data\Teammate;

use App\Enums\UserOnlineStatus;
use App\Enums\UserPermission;
use App\Models\User;
use App\Settings\GeneralSettings;
use Spatie\LaravelData\Data;

/**
 * 客服资料。
 */
class TeammateData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $nickname,
        public ?string $avatar,
        public string $email,
        public string $locale,
        public bool $is_owner,
        public UserOnlineStatus $online_status,
        /** @var list<string> */
        public array $permissions = [],
    ) {}

    public static function fromModel(User $user): self
    {
        $settings = app(GeneralSettings::class);
        $membership = $user->membership;
        $isOwner = filled($settings->owner_id) && (string) $settings->owner_id === (string) $user->id;
        $onlineStatus = UserOnlineStatus::from((int) $membership->online_status);

        return new self(
            id: (string) $user->id,
            name: $user->name,
            nickname: filled($membership->nickname) ? (string) $membership->nickname : null,
            avatar: filled($user->avatar) ? $user->avatar : null,
            email: $user->email,
            locale: $user->locale,
            is_owner: $isOwner,
            online_status: $onlineStatus,
            permissions: $isOwner
                ? UserPermission::values()
                : array_values(array_filter(array_map('strval', $user->permissions ?? []))),
        );
    }
}
