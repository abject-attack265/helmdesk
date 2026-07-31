<?php

namespace App\Actions\User;

use App\Data\Teammate\FormUpdateTeammateOnlineStatusData;
use App\Models\Membership;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 更新当前用户在线状态。
 */
class UpdateMyOnlineStatusAction
{
    use AsAction;

    /**
     * 保存当前用户在应用内的在线状态。
     */
    public function handle(string $userId, FormUpdateTeammateOnlineStatusData $data): void
    {
        Membership::query()->whereKey($userId)->firstOrFail()->update([
            'online_status' => $data->online_status,
        ]);
    }

    /**
     * 接收当前用户在线状态更新请求。
     */
    public function asController(Request $request)
    {
        $data = FormUpdateTeammateOnlineStatusData::from($request);
        $this->handle($request->user()->id, $data);

        return back();
    }
}
