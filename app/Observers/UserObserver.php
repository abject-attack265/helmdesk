<?php

namespace App\Observers;

use App\Models\CannedReply;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * 物理删除用户时软删除其个人快捷回复，避免保留无归属用户的私有内容。
 */
class UserObserver
{
    /**
     * 要求用户与个人快捷回复在同一数据库事务中删除。
     */
    public function forceDeleting(User $user): void
    {
        if ($user->getConnection()->transactionLevel() === 0) {
            Log::warning('用户物理删除缺少事务边界', [
                'user_id' => (string) $user->id,
            ]);

            throw new LogicException('用户与个人快捷回复必须在同一数据库事务中删除。');
        }

        if (User::withTrashed()->whereKey($user->id)->exists()) {
            return;
        }

        Log::warning('物理删除的用户记录不存在', [
            'user_id' => (string) $user->id,
        ]);

        throw new LogicException('物理删除的用户必须存在。');
    }

    /**
     * 物理删除用户时软删除其个人快捷回复。
     */
    public function forceDeleted(User $user): void
    {
        CannedReply::query()
            ->where('user_id', $user->id)
            ->delete();
    }
}
