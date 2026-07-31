<?php

namespace App\Data;

use App\Enums\UserOnlineStatus;
use App\Enums\UserPermission;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use RuntimeException;
use Spatie\LaravelData\Data;

/**
 * 当前登录用户上下文。
 */
class CurrentUserContextData extends Data
{
    public function __construct(
        public string $app_name,
        public string $user_id,
        public string $user_name,
        public string $user_email,
        public EnumOptionData $user_online_status,
        public bool $is_owner,
        public bool $can_delete = true,
        public ?string $user_nickname = null,
        public ?string $user_last_active_at = null,
        public ?string $user_avatar = null,
        public int $permission_count = 0,
        public bool $can_edit = true,
    ) {}

    public static function fromUser(User $user): self
    {
        $settings = app(GeneralSettings::class);
        $isOwner = filled($settings->owner_id) && (string) $settings->owner_id === (string) $user->id;

        $membership = $user->membership;

        if ($membership === null) {
            throw new RuntimeException('User membership is not initialized.');
        }

        $onlineStatusEnum = UserOnlineStatus::from((int) $membership->online_status);
        $lastActiveAt = filled($membership->last_active_at) ? Carbon::parse($membership->last_active_at)->toIso8601String() : null;

        return new self(
            app_name: $settings->name,
            user_id: (string) $user->id,
            user_name: $user->name,
            user_email: $user->email,
            user_avatar: filled($user->avatar) ? $user->avatar : null,
            user_online_status: EnumOptionData::fromEnum($onlineStatusEnum),
            user_nickname: filled($membership->nickname) ? (string) $membership->nickname : null,
            user_last_active_at: $lastActiveAt,
            is_owner: $isOwner,
            permission_count: $isOwner ? count(UserPermission::cases()) : count($user->permissions ?? []),
        );
    }

    public static function fromRequest(Request $request): self
    {
        $ctx = $request->attributes->get(self::class);

        if (! $ctx instanceof self) {
            throw new RuntimeException('Current user context is not set.');
        }

        return $ctx;
    }

    public static function tryFromRequest(Request $request): ?self
    {
        $ctx = $request->attributes->get(self::class);

        return $ctx instanceof self ? $ctx : null;
    }

    public function withTeammateActions(bool $canEdit, bool $canDelete): self
    {
        $this->can_edit = $canEdit;
        $this->can_delete = $canDelete;

        return $this;
    }
}
