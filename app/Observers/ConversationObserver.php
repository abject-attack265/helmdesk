<?php

namespace App\Observers;

use App\Actions\Conversation\SyncConversationThreadAction;
use App\Enums\ConversationStatus;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * 维护会话联系人渠道身份约束与收件箱线程投影。
 */
class ConversationObserver
{
    /** 会话在线程中的稳定身份字段。 */
    private const array IDENTITY_FIELDS = ['contact_id', 'channel_id'];

    /** 需要同步到线程投影的会话字段。 */
    private const array PROJECTION_FIELDS = [
        'status',
        'inbox_status',
        'assigned_user_id',
        'last_message_at',
        'closed_at',
        'reopened_at',
    ];

    /**
     * 注入收件箱线程同步动作。
     */
    public function __construct(
        private readonly SyncConversationThreadAction $syncConversationThreadAction,
    ) {}

    /**
     * 校验新会话的关闭时间、事务边界和线程身份引用。
     */
    public function creating(Conversation $conversation): void
    {
        $this->assertClosedConversationHasTimestamp($conversation);

        if ($conversation->contact_id === null || $conversation->channel_id === null) {
            return;
        }

        $this->assertThreadWriteIsTransactional($conversation, 'create');

        $contactExists = Contact::withTrashed()
            ->whereKey($conversation->contact_id)
            ->exists();
        $channelExists = Channel::withTrashed()
            ->whereKey($conversation->channel_id)
            ->exists();

        if ($contactExists && $channelExists) {
            return;
        }

        Log::warning('会话线程身份引用无效', [
            'contact_id' => (string) $conversation->contact_id,
            'channel_id' => (string) $conversation->channel_id,
            'contact_valid' => $contactExists,
            'channel_valid' => $channelExists,
        ]);

        throw new LogicException('会话引用的联系人或渠道不存在。');
    }

    /**
     * 校验会话更新的关闭时间、稳定线程身份和投影事务边界。
     */
    public function updating(Conversation $conversation): void
    {
        $this->assertClosedConversationHasTimestamp($conversation);

        $changedFields = array_values(array_intersect(
            self::IDENTITY_FIELDS,
            array_keys($conversation->getDirty()),
        ));

        if ($changedFields !== []) {
            Log::warning('会话线程身份字段被直接修改', [
                'conversation_id' => (string) $conversation->id,
                'changed_fields' => $changedFields,
            ]);

            throw new LogicException(
                '会话线程身份字段不可通过模型更新：'.implode(', ', $changedFields),
            );
        }

        if (
            $conversation->contact_id !== null
            && $conversation->channel_id !== null
            && array_intersect(self::PROJECTION_FIELDS, array_keys($conversation->getDirty())) !== []
        ) {
            $this->assertThreadWriteIsTransactional($conversation, 'update');
        }
    }

    /**
     * 校验完整线程身份的会话删除处于事务内。
     */
    public function deleting(Conversation $conversation): void
    {
        if ($conversation->contact_id === null || $conversation->channel_id === null) {
            return;
        }

        $this->assertThreadWriteIsTransactional($conversation, 'delete');
    }

    /**
     * 会话创建后建立联系人渠道线程。
     */
    public function created(Conversation $conversation): void
    {
        if ($conversation->contact_id === null || $conversation->channel_id === null) {
            return;
        }

        $this->syncConversationThreadAction->establish($conversation);
    }

    /**
     * 会话投影字段变化后重算联系人渠道线程。
     */
    public function updated(Conversation $conversation): void
    {
        if ($conversation->contact_id === null || $conversation->channel_id === null) {
            return;
        }

        if (! $conversation->wasChanged(self::PROJECTION_FIELDS)) {
            return;
        }

        $this->syncConversationThreadAction->handle($conversation);
    }

    /**
     * 会话删除后重算原联系人渠道线程，空线程随之删除。
     */
    public function deleted(Conversation $conversation): void
    {
        if ($conversation->contact_id === null || $conversation->channel_id === null) {
            return;
        }

        $this->syncConversationThreadAction->forget(
            contactId: (string) $conversation->contact_id,
            channelId: (string) $conversation->channel_id,
        );
    }

    /**
     * 要求已关闭会话同时保存关闭时间。
     */
    private function assertClosedConversationHasTimestamp(Conversation $conversation): void
    {
        if (
            $conversation->status === ConversationStatus::Closed
            && $conversation->closed_at === null
        ) {
            Log::warning('已关闭会话缺少关闭时间', [
                'conversation_id' => (string) $conversation->id,
            ]);

            throw new LogicException('已关闭会话必须包含关闭时间。');
        }
    }

    /**
     * 要求会话与线程投影在同一数据库事务中提交。
     */
    private function assertThreadWriteIsTransactional(
        Conversation $conversation,
        string $operation,
    ): void {
        if ($conversation->getConnection()->transactionLevel() > 0) {
            return;
        }

        Log::warning('会话线程投影写入缺少事务边界', [
            'operation' => $operation,
            'conversation_id' => (string) $conversation->id,
            'contact_id' => (string) $conversation->contact_id,
            'channel_id' => (string) $conversation->channel_id,
        ]);

        throw new LogicException('完整会话的线程投影写入必须处于数据库事务中。');
    }
}
