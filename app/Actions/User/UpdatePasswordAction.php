<?php

namespace App\Actions\User;

use App\Data\User\FormUpdatePasswordData;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 更新当前用户密码。
 */
class UpdatePasswordAction
{
    use AsAction;

    public function handle(User $user, FormUpdatePasswordData $data): void
    {
        $user->update([
            'password' => $data->password,
        ]);
    }

    public function asController(Request $request): RedirectResponse
    {
        $this->handle($request->user(), FormUpdatePasswordData::from($request));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('common.操作成功'),
        ]);

        return back();
    }
}
