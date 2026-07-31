<?php

namespace App\Actions\Teammate;

use App\Actions\Invitation\SendInvitationAction;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 刷新待接受邀请的令牌并重新发送邮件。
 */
class ResendInvitationAction
{
    use AsAction;

    /**
     * 重发当前用户有权管理的邀请。
     */
    public function handle(User $actor, Invitation $invitation): void
    {
        $authorization = Gate::forUser($actor)->inspect('users.manageInvitation', $invitation);

        if ($authorization->denied()) {
            Log::warning('成员邀请重发被权限规则拒绝。', [
                'actor_user_id' => $actor->id,
                'invitation_id' => $invitation->id,
            ]);
        }

        $authorization->authorize();

        $plainToken = Str::random(64);

        $invitation->forceFill([
            'token' => Invitation::hashToken($plainToken),
            'expires_at' => now()->addDays(Invitation::EXPIRES_AFTER_DAYS),
        ])->save();

        SendInvitationAction::run($invitation, $plainToken);
    }

    /**
     * 查询待接受邀请并执行重发。
     */
    public function asController(Request $request, string $invitation)
    {
        $target = Invitation::query()
            ->pending()
            ->findOrFail($invitation);

        $this->handle($request->user(), $target);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('teammate.invitation.resent'),
        ]);

        return redirect()->route('app.manage.teammates.index');
    }
}
