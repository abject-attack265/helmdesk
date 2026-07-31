<?php

namespace App\Actions\Teammate;

use App\Data\Teammate\PermissionGroupData;
use App\Data\Teammate\ShowCreateTeammatePagePropsData;
use App\Enums\UserPermission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowCreateTeammatePageAction
{
    use AsAction;

    public function handle(User $actor): ShowCreateTeammatePagePropsData
    {
        return new ShowCreateTeammatePagePropsData(
            permission_groups: PermissionGroupData::allGroups(),
            can_assign_permissions: Gate::forUser($actor)->allows('app.owner'),
        );
    }

    public function asController(Request $request)
    {
        Gate::forUser($request->user())->authorize('user.permission', UserPermission::UsersCreate);

        return Inertia::render('teammate/Create', $this->handle($request->user())->toArray());
    }
}
