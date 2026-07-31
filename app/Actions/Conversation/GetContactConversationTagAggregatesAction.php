<?php

namespace App\Actions\Conversation;

use App\Data\Conversation\ContactConversationTagAggregateData;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 计算联系人「咨询概况」：跨该联系人所有会话，把会话标签去重计数（仅统计未被抑制的有效标签）。
 */
class GetContactConversationTagAggregatesAction
{
    use AsAction;

    /**
     * 跨联系人所有会话按标签去重计数，倒序返回聚合项。
     *
     * @return list<ContactConversationTagAggregateData>
     */
    public function handle(string $contactId): array
    {
        return DB::table('conversation_tag_assignments as cta')
            ->join('conversations as c', 'c.id', '=', 'cta.conversation_id')
            ->join('tags as t', 't.id', '=', 'cta.tag_id')
            ->where('c.contact_id', $contactId)
            ->whereNull('cta.removed_at')
            ->whereNull('t.deleted_at')
            ->groupBy('t.id', 't.name', 't.color')
            ->orderByDesc('count')
            ->orderBy('t.name')
            ->select('t.id', 't.name', 't.color', DB::raw('count(*) as count'))
            ->get()
            ->map(fn ($row) => new ContactConversationTagAggregateData(
                tag_id: $row->id,
                name: $row->name,
                color: $row->color,
                count: (int) $row->count,
            ))
            ->all();
    }
}
