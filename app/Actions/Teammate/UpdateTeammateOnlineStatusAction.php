<?php

namespace App\Actions\Teammate;

use App\Data\Teammate\FormUpdateTeammateOnlineStatusData;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 在后台设置更新指定客服的接待状态。
 */
class UpdateTeammateOnlineStatusAction
{
    use AsAction;

    /**
     * 更新指定客服的接待状态。
     */
    public function handle(User $actor, string $id, FormUpdateTeammateOnlineStatusData $data): void
    {
        $user = User::query()->whereHas('membership')->whereKey($id)->firstOrFail();
        $authorization = Gate::forUser($actor)->inspect('users.updateMember', $user);

        if ($authorization->denied()) {
            Log::warning('成员在线状态更新被权限规则拒绝。', [
                'actor_user_id' => $actor->id,
                'target_user_id' => $user->id,
            ]);
        }

        $authorization->authorize();

        $user->membership()->update([
            'online_status' => $data->online_status,
        ]);
    }

    /**
     * 将在线状态表单转换为 Data 并更新指定成员。
     */
    public function asController(Request $request, string $id)
    {
        $data = FormUpdateTeammateOnlineStatusData::from($request);
        $this->handle($request->user(), $id, $data);

        return back();
    }
}
