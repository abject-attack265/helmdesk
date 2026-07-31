<?php

namespace App\Actions\Teammate;

use App\Data\CurrentUserContextData;
use App\Data\EnumOptionData;
use App\Data\Invitation\ListInvitationItemData;
use App\Data\Teammate\ShowListTeammatePagePropsData;
use App\Enums\UserOnlineStatus;
use App\Enums\UserPermission;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 查询客服账号和待接受邀请，并返回可执行的操作。
 */
class ShowTeammateListAction
{
    use AsAction;

    /**
     * 按搜索条件与在线状态筛选成员和待接受邀请。
     */
    public function handle(
        User $actor,
        string $search = '',
        string $onlineStatus = 'all',
    ): ShowListTeammatePagePropsData {
        Gate::forUser($actor)->authorize('user.permission', UserPermission::UsersView);

        $search = trim($search);

        $query = User::query()->whereHas('membership')->with('membership');

        if ($search !== '') {
            $query->where(function (Builder $scope) use ($search): void {
                $scope->where('users.name', 'like', '%'.$search.'%')
                    ->orWhere('users.email', 'like', '%'.$search.'%');
            });
        }

        if ($onlineStatus !== 'all' && is_numeric($onlineStatus)) {
            $query->whereHas('membership', fn (Builder $membership) => $membership->where('online_status', (int) $onlineStatus));
        }

        $users = $query->orderBy('users.id', 'asc')->get();

        $userList = $users->map(function ($u) use ($actor) {
            return CurrentUserContextData::fromUser($u)->withTeammateActions(
                Gate::forUser($actor)->allows('users.updateMember', $u),
                Gate::forUser($actor)->allows('users.removeMember', $u),
            );
        })->all();

        $pendingInvitations = Invitation::query()
            ->pending()
            ->with('inviter')
            ->latest()
            ->get()
            ->map(static fn (Invitation $invitation): ListInvitationItemData => ListInvitationItemData::fromModel(
                $invitation,
                Gate::forUser($actor)->allows('users.manageInvitation', $invitation),
            ))
            ->all();

        return new ShowListTeammatePagePropsData(
            user_list: $userList,
            pending_invitations: $pendingInvitations,
            online_status_options: EnumOptionData::fromCases(UserOnlineStatus::cases()),
            current_search: $search,
            current_online_status: $onlineStatus,
            can_create: Gate::forUser($actor)->allows('user.permission', UserPermission::UsersCreate),
        );
    }

    /**
     * 返回成员列表页面。
     */
    public function asController(Request $request)
    {
        $search = $request->query('search');
        $onlineStatus = $request->query('online_status');

        $props = $this->handle(
            $request->user(),
            is_string($search) ? $search : '',
            is_string($onlineStatus) && $onlineStatus !== '' ? $onlineStatus : 'all',
        );

        return Inertia::render('teammate/List', $props->toArray());
    }
}
