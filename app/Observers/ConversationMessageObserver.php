<?php

namespace App\Observers;

use App\Actions\Channel\DispatchOutboundMessageAction;
use App\Actions\Conversation\QueueConversationSubjectGenerationAction;
use App\Actions\Conversation\RecordConversationTimelineEntryAction;
use App\Enums\ConversationTimelineEntryType;
use App\Models\ConversationMessage;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * 维护消息角色与类型约束、会话内序号，并在创建后写入时间线索引和触发后续动作。
 */
class ConversationMessageObserver
{
    /**
     * 校验事务边界及消息角色与内容类型组合，并分配会话内单调序号。
     */
    public function creating(ConversationMessage $message): void
    {
        $this->assertTimelineWriteIsTransactional($message);
        $this->assertRoleAllowsKind($message);
        $message->seq_no = ConversationMessage::allocateSeqNo($message->conversation_id);
    }

    /**
     * 写入消息时间线索引，并触发渠道投递与主题生成条件判断。
     */
    public function created(ConversationMessage $message): void
    {
        RecordConversationTimelineEntryAction::run(
            entryType: ConversationTimelineEntryType::Message,
            entryId: (string) $message->id,
            conversationId: (string) $message->conversation_id,
            occurredAt: $message->created_at,
        );

        DispatchOutboundMessageAction::run($message);
        QueueConversationSubjectGenerationAction::run($message);
    }

    /**
     * 校验更新后的消息角色与内容类型组合。
     */
    public function updating(ConversationMessage $message): void
    {
        $this->assertRoleAllowsKind($message);
    }

    /**
     * 要求消息角色允许当前内容类型。
     */
    private function assertRoleAllowsKind(ConversationMessage $message): void
    {
        if (! $message->role->allowsKind($message->kind)) {
            throw ValidationException::withMessages([
                'kind' => __('conversation.errors.invalid_role_kind_combination'),
            ]);
        }
    }

    /**
     * 要求消息与时间线索引在同一数据库事务中创建。
     */
    private function assertTimelineWriteIsTransactional(ConversationMessage $message): void
    {
        if ($message->getConnection()->transactionLevel() > 0) {
            return;
        }

        Log::warning('会话消息与时间线索引写入缺少事务边界', [
            'conversation_id' => (string) $message->conversation_id,
            'role' => $message->role->value,
            'kind' => $message->kind->value,
        ]);

        throw new LogicException('会话消息与时间线索引必须在同一数据库事务中创建。');
    }
}
