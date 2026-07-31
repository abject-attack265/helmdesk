<?php

namespace App\Observers;

use App\Actions\Conversation\SyncContactConversationThreadImportanceAction;
use App\Models\Contact;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * 维护联系人重点标记与收件箱线程排序投影。
 */
class ContactObserver
{
    /**
     * 要求重点标记与线程投影在同一数据库事务中提交。
     */
    public function updating(Contact $contact): void
    {
        if (! $contact->isDirty('is_important')) {
            return;
        }

        if ($contact->getConnection()->transactionLevel() > 0) {
            return;
        }

        Log::warning('联系人重点标记投影写入缺少事务边界', [
            'contact_id' => (string) $contact->id,
            'original_is_important' => (bool) $contact->getOriginal('is_important'),
            'new_is_important' => (bool) $contact->is_important,
        ]);

        throw new LogicException('联系人重点标记与线程投影必须在同一数据库事务中更新。');
    }

    /**
     * 重点标记变化后同步联系人全部收件箱线程。
     */
    public function updated(Contact $contact): void
    {
        if (! $contact->wasChanged('is_important')) {
            return;
        }

        SyncContactConversationThreadImportanceAction::run($contact);
    }
}
