<?php

namespace App\Actions\User;

use App\Data\User\FormDeleteProfileData;
use App\Exceptions\BusinessException;
use App\Models\User;
use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * 删除当前用户账号。
 */
class DeleteProfileAction
{
    use AsAction;

    /**
     * 永久删除用户；系统所有者不可删除，避免系统失去管理员。
     */
    public function handle(User $user): void
    {
        $this->assertDeletable($user);

        DB::transaction(function () use ($user): void {
            $user->forceDelete();
        });
    }

    /**
     * 拒绝删除系统所有者。
     */
    public function assertDeletable(User $user): void
    {
        if ((string) app(GeneralSettings::class)->owner_id === (string) $user->id) {
            throw new BusinessException(__('user.cannot_delete_owner'));
        }
    }

    /**
     * 注销后通过 Inertia 整页跳转到首页。
     */
    public function asController(Request $request): Response
    {
        FormDeleteProfileData::from($request);

        $user = $request->user();

        // 删除资格校验先于登出，拒绝注销时保留登录态。
        $this->assertDeletable($user);

        // Auth::logout() 可能保存新的 remember_token，因此必须在物理删除前执行。
        Auth::logout();

        $this->handle($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location(route('home', absolute: false));
    }
}
