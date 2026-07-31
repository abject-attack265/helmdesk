<?php

namespace App\Actions\User;

use App\Enums\UserOnlineStatus;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 将长时间未活动仍标记在线的应用成员自动置为离线。
 *
 * 与 TouchSystemUserLastActiveAtAction 配套：后者按请求刷新 last_active_at，
 * 本动作据此把超过阈值无活动（含从未活动）的在线成员降级为离线，避免收件箱误派给不在场的坐席。
 */
class OfflineInactiveInstanceMembersAction
{
    use AsAction;

    /** 成员超过该不活跃时长（分钟）即自动离线。 */
    public const int INACTIVE_THRESHOLD_MINUTES = 10;

    /**
     * 把所有超过不活跃阈值（或从未活动）仍标记在线的成员置为离线，返回受影响行数。
     */
    public function handle(): int
    {
        return DB::table('memberships')
            ->where('online_status', UserOnlineStatus::Online)
            ->where(function ($query) {
                $query->whereNull('last_active_at')
                    ->orWhere('last_active_at', '<', now()->subMinutes(self::INACTIVE_THRESHOLD_MINUTES));
            })
            ->update(['online_status' => UserOnlineStatus::Offline]);
    }
}
