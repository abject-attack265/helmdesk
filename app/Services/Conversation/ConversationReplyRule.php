<?php

namespace App\Services\Conversation;

use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Models\Conversation;
use App\Models\User;

/**
 * 统一判断客服是否可以直接回复会话。
 */
class ConversationReplyRule
{
    /**
     * 判断当前客服是否可以直接回复会话。
     */
    public function canReply(Conversation $conversation, User $user): bool
    {
        return $this->denialMessageKey($conversation, $user) === null;
    }

    /**
     * 返回不可回复时对应的业务错误文案 key。
     */
    public function denialMessageKey(Conversation $conversation, User $user): ?string
    {
        if ($conversation->status !== ConversationStatus::Open) {
            return 'conversation.errors.already_closed';
        }

        if (
            $conversation->assigned_user_id === null
            && $conversation->inbox_status === ConversationInboxStatus::AiHandling
        ) {
            return 'conversation.errors.transfer_to_human_required_before_reply';
        }

        if (
            $conversation->assigned_user_id !== null
            && (string) $conversation->assigned_user_id !== (string) $user->id
        ) {
            return 'conversation.errors.reply_not_allowed_for_assignee';
        }

        return null;
    }
}
