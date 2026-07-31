<?php

namespace App\Actions\Invitation;

use App\Data\Invitation\FormAcceptInvitationData;
use App\Enums\UserOnlineStatus;
use App\Exceptions\BusinessException;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Services\LocalePreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 接受公开成员邀请，创建账号并加入当前系统。
 */
class AcceptInvitationAction
{
    use AsAction;

    /**
     * 锁定邀请行后创建已验证账号、建立成员关系并标记邀请已接受。
     */
    public function handle(Invitation $invitation, FormAcceptInvitationData $data, string $locale): User
    {
        $user = DB::transaction(function () use ($invitation, $data, $locale): User {
            // 行锁保证邀请撤销与并发接受按顺序处理。
            $locked = Invitation::query()
                ->whereKey($invitation->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked === null || ! $locked->isAcceptable()) {
                throw new BusinessException(__('teammate.invitation.not_acceptable'));
            }

            // 软删除账号仍占用邮箱唯一索引。
            if (User::withTrashed()->where('email', $locked->email)->exists()) {
                throw new BusinessException(__('teammate.invitation.email_taken'));
            }

            $user = User::query()->create([
                'name' => $data->name,
                'email' => $locked->email,
                'password' => $data->password,
                'locale' => $locale,
                'permissions' => $locked->permissions,
            ]);

            // 接受邀请后将受邀邮箱标记为已验证。
            $user->forceFill(['email_verified_at' => now()])->save();

            Membership::query()->create([
                'user_id' => $user->id,
                'nickname' => filled($locked->nickname) ? $locked->nickname : null,
                'online_status' => UserOnlineStatus::Online,
            ]);

            $locked->forceFill(['accepted_at' => now()])->save();

            return $user;
        });

        Log::info('user.invitation.accepted', [
            'invitation_id' => (string) $invitation->id,
            'user_id' => (string) $user->id,
        ]);

        Auth::guard('web')->login($user);

        return $user;
    }

    /**
     * 接收邀请表单并将已登录用户跳转到应用工作台。
     */
    public function asController(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::findByPlainToken($token);

        if ($invitation === null || ! $invitation->isAcceptable()) {
            throw new BusinessException(__('teammate.invitation.not_acceptable'));
        }

        $data = FormAcceptInvitationData::from($request);
        $locale = LocalePreference::normalizeFrontend(LocalePreference::preferredBrowserLocale($request));

        $user = $this->handle($invitation, $data, $locale);

        return redirect()->route('app.dashboard');
    }
}
