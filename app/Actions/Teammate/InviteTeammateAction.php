<?php

namespace App\Actions\Teammate;

use App\Actions\Invitation\SendInvitationAction;
use App\Data\Teammate\FormInviteTeammateData;
use App\Enums\UserPermission;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class InviteTeammateAction
{
    use AsAction;

    public function handle(FormInviteTeammateData $data, User $inviter): Invitation
    {
        Gate::forUser($inviter)->authorize('user.permission', UserPermission::UsersCreate);
        $permissions = Gate::forUser($inviter)->allows('app.owner') ? $data->permissions : [];

        $plainToken = Str::random(64);

        $invitation = Invitation::query()->updateOrCreate(
            [
                'email' => $data->email,
            ],
            [
                'nickname' => filled($data->nickname) ? $data->nickname : null,
                'permissions' => $permissions,
                'invited_by' => $inviter->id,
                'token' => Invitation::hashToken($plainToken),
                'expires_at' => now()->addDays(Invitation::EXPIRES_AFTER_DAYS),
                'accepted_at' => null,
            ],
        );

        SendInvitationAction::run($invitation, $plainToken);

        return $invitation;
    }

    public function asController(Request $request)
    {
        $data = FormInviteTeammateData::from($request);
        $this->handle($data, $request->user());

        return redirect()->route('app.manage.teammates.index');
    }
}
