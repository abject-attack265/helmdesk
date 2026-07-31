<?php

namespace App\Actions\Teammate;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 撤销待接受邀请。
 */
class RevokeInvitationAction
{
    use AsAction;

    /**
     * 撤销当前用户有权管理的邀请。
     */
    public function handle(User $actor, Invitation $invitation): void
    {
        $authorization = Gate::forUser($actor)->inspect('users.manageInvitation', $invitation);

        if ($authorization->denied()) {
            Log::warning('成员邀请撤销被权限规则拒绝。', [
                'actor_user_id' => $actor->id,
                'invitation_id' => $invitation->id,
            ]);
        }

        $authorization->authorize();

        $invitation->delete();
    }

    /**
     * 查询待接受邀请并执行撤销。
     */
    public function asController(Request $request, string $invitation)
    {
        $target = Invitation::query()
            ->pending()
            ->findOrFail($invitation);

        $this->handle($request->user(), $target);

        return redirect()->route('app.manage.teammates.index');
    }
}
