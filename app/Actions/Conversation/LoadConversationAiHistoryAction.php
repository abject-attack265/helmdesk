<?php

namespace App\Actions\Conversation;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 加载指定会话中可进入 AI 上下文的访客、AI 和人工客服消息。
 *
 * 消息按 seq_no 升序返回，图片和文件消息预载附件。已撤回、无内容以及不参与 AI 历史的自动消息不返回。
 */
class LoadConversationAiHistoryAction
{
    use AsAction;

    /**
     * 按 seq_no 升序返回全部可进入模型上下文的会话消息。
     *
     * @param  list<string>  $excludeIds  查询前排除的当前轮消息 ID
     * @return Collection<int, ConversationMessage>
     */
    public function handle(Conversation $conversation, array $excludeIds = []): Collection
    {
        return ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->when($excludeIds !== [], fn ($query) => $query->whereNotIn('id', $excludeIds))
            ->whereIn('role', [MessageRole::Visitor, MessageRole::Ai, MessageRole::Teammate])
            ->whereIn('kind', [MessageKind::Text, MessageKind::Image, MessageKind::File])
            ->whereNull('recalled_at')
            ->with('attachments.storageProfile')
            ->orderBy('seq_no')
            ->get()
            ->filter(fn (ConversationMessage $message): bool => $message->isUsableAiHistoryMessage())
            ->values();
    }
}
