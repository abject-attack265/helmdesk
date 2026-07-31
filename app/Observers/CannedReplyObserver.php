<?php

namespace App\Observers;

use App\Models\CannedReply;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * 维护个人快捷回复的用户归属约束。
 */
class CannedReplyObserver
{
    /**
     * 保存个人快捷回复前校验事务边界和归属用户。
     */
    public function saving(CannedReply $reply): void
    {
        if ($reply->user_id === null) {
            return;
        }

        if ($reply->getConnection()->transactionLevel() === 0) {
            Log::warning('个人快捷回复写入缺少事务边界', [
                'operation' => $reply->exists ? 'update' : 'create',
                'canned_reply_id' => (string) $reply->id,
                'user_id' => (string) $reply->user_id,
            ]);

            throw new LogicException('个人快捷回复与归属用户必须在同一数据库事务中写入。');
        }

        if (User::query()->whereKey($reply->user_id)->exists()) {
            return;
        }

        Log::warning('个人快捷回复引用无效用户', [
            'operation' => $reply->exists ? 'update' : 'create',
            'canned_reply_id' => (string) $reply->id,
            'user_id' => (string) $reply->user_id,
        ]);

        throw new LogicException('个人快捷回复必须归属于有效用户。');
    }
}
