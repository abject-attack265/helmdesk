<?php

namespace App\Policies;

use App\Models\CannedReply;
use App\Models\User;

/**
 * 快捷回复访问规则。
 * 个人模版仅作者本人可改；应用共享模版对所有成员可改。
 */
class CannedReplyPolicy
{
    /**
     * 能否查看某条模版：应用共享对所有成员可见，个人模版仅本人可见。
     */
    public function view(User $user, CannedReply $reply): bool
    {
        return $reply->isInstanceShared() || $reply->isOwnedBy($user);
    }

    /**
     * 能否编辑某条模版：本人的个人模版或应用共享模版。
     */
    public function update(User $user, CannedReply $reply): bool
    {
        return $reply->isOwnedBy($user) || $reply->isInstanceShared();
    }

    /**
     * 删除策略与编辑一致。
     */
    public function delete(User $user, CannedReply $reply): bool
    {
        return $this->update($user, $reply);
    }
}
