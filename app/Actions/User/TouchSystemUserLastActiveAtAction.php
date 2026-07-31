<?php

namespace App\Actions\User;

use App\Models\Membership;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 刷新应用成员的最后活跃时间。
 */
class TouchSystemUserLastActiveAtAction
{
    use AsAction;

    private const int TOUCH_INTERVAL_MINUTES = 1;

    /**
     * 按最小间隔刷新成员在应用内的最后活跃时间。
     */
    public function handle(string $userId): void
    {
        $lastActiveAt = Membership::query()
            ->whereKey($userId)
            ->value('last_active_at');

        if ($lastActiveAt !== null && Carbon::parse($lastActiveAt)->greaterThan(now()->subMinutes(self::TOUCH_INTERVAL_MINUTES))) {
            return;
        }

        Membership::query()->whereKey($userId)->update([
            'last_active_at' => now(),
        ]);
    }
}
