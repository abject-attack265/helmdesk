<?php

namespace App\Actions\Teammate;

use App\Data\Teammate\PermissionGroupData;
use App\Data\Teammate\ShowInviteTeammatePagePropsData;
use App\Enums\UserPermission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowInviteTeammatePageAction
{
    use AsAction;

    public function handle(User $actor): ShowInviteTeammatePagePropsData
    {
        return new ShowInviteTeammatePagePropsData(
            permission_groups: PermissionGroupData::allGroups(),
            can_assign_permissions: Gate::forUser($actor)->allows('app.owner'),
        );
    }

    public function asController(Request $request)
    {
        Gate::forUser($request->user())->authorize('user.permission', UserPermission::UsersCreate);

        return Inertia::render('teammate/Invite', $this->handle($request->user())->toArray());
    }
}
