<?php

namespace App\Actions\Reception;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 加载联系人的其他会话消息。
 */
class LoadContactConversationHistoryAction
{
    use AsAction;

    /**
     * 按时间顺序返回该联系人的全部其他会话消息。
     *
     * @return Collection<int, ConversationMessage>
     */
    public function handle(Conversation $conversation): Collection
    {
        if ($conversation->contact_id === null) {
            return new Collection;
        }

        return ConversationMessage::query()
            ->whereHas('conversation', function (Builder $query) use ($conversation): void {
                $query
                    ->where('contact_id', $conversation->contact_id)
                    ->whereKeyNot($conversation->id);
            })
            ->whereIn('role', [MessageRole::Visitor, MessageRole::Ai, MessageRole::Teammate])
            ->whereIn('kind', [MessageKind::Text, MessageKind::Image, MessageKind::File])
            ->whereNull('recalled_at')
            ->with('attachments.storageProfile')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (ConversationMessage $message): bool => $message->isUsableAiHistoryMessage())
            ->values();
    }
}
