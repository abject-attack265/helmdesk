<?php

namespace App\Actions\Teammate;

use App\Data\Teammate\PermissionGroupData;
use App\Data\Teammate\ShowEditTeammatePagePropsData;
use App\Data\Teammate\TeammateData;
use App\Enums\UserPermission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 展示客服成员编辑页面。
 */
class ShowEditTeammatePageAction
{
    use AsAction;

    /**
     * 查询目标成员并计算当前用户可执行的编辑操作。
     */
    public function handle(User $actor, string $id): ShowEditTeammatePagePropsData
    {
        Gate::forUser($actor)->authorize('user.permission', UserPermission::UsersEdit);

        $user = User::query()->whereKey($id)->whereHas('membership')->firstOrFail();
        $canUpdateProfile = Gate::forUser($actor)->allows('users.updateMember', $user);
        $actorIsOwner = Gate::forUser($actor)->allows('app.owner');

        return new ShowEditTeammatePagePropsData(
            user_form: TeammateData::fromModel($user),
            permission_groups: PermissionGroupData::allGroups(),
            can_update_profile: $canUpdateProfile,
            can_update_credentials: $canUpdateProfile && $actorIsOwner,
            can_assign_permissions: $canUpdateProfile && $actorIsOwner,
        );
    }

    /**
     * 返回成员编辑页面。
     */
    public function asController(Request $request, string $id)
    {
        $props = $this->handle($request->user(), $id);

        return Inertia::render('teammate/Edit', $props->toArray());
    }
}
