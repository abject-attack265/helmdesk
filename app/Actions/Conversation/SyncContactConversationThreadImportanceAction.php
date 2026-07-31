<?php

namespace App\Actions\Conversation;

use App\Models\Contact;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 把联系人重点标记同步到其收件箱线程排序投影。
 */
class SyncContactConversationThreadImportanceAction
{
    use AsAction;

    /**
     * 更新联系人全部线程的重点排序字段。
     */
    public function handle(Contact $contact): void
    {
        DB::table('conversation_threads')
            ->where('contact_id', $contact->id)
            ->update(['is_important' => $contact->is_important]);
    }
}
