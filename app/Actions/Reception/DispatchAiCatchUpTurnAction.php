<?php

namespace App\Actions\Reception;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 会话切回 AI 接待后，用最后一次实质回复之后的访客消息触发一轮补答。
 */
class DispatchAiCatchUpTurnAction
{
    use AsAction;

    /** 一轮补答最多携带的积压访客消息条数。 */
    private const int MAX_MESSAGES = 20;

    /**
     * 注入接待轮次派发 Action。
     */
    public function __construct(
        private readonly DispatchReceptionTurnAction $dispatchTurn,
    ) {}

    /**
     * 找出积压的访客消息并派发一轮补答 turn；返回是否派发。
     */
    public function handle(Conversation $conversation): bool
    {
        $pending = $this->pendingVisitorMessages($conversation);
        if ($pending === []) {
            return false;
        }

        $texts = [];
        $messageIds = [];
        $mediaIds = [];
        foreach ($pending as $message) {
            if ($message->kind === MessageKind::Image) {
                $mediaIds[] = (string) $message->id;

                continue;
            }

            if (filled($message->content)) {
                $texts[] = (string) $message->content;
                $messageIds[] = (string) $message->id;
            }
        }

        if ($texts === [] && $mediaIds === []) {
            return false;
        }

        $this->dispatchTurn->handle(
            (string) $conversation->id,
            implode("\n", $texts),
            $messageIds,
            $mediaIds,
        );

        return true;
    }

    /**
     * 返回最后一次实质回复之后的积压访客消息。
     *
     * 人工消息及带 turn_id 的 AI 消息构成实质回复；其他 AI 提示消息不截断补答范围。
     *
     * @return list<ConversationMessage>
     */
    private function pendingVisitorMessages(Conversation $conversation): array
    {
        $lastRepliedSeqNo = ConversationMessage::query()

            ->where('conversation_id', $conversation->id)
            ->where(fn ($query) => $query
                ->where('role', MessageRole::Teammate)
                ->orWhere(fn ($aiQuery) => $aiQuery
                    ->where('role', MessageRole::Ai)
                    ->whereNotNull('turn_id')))
            ->max('seq_no');

        return ConversationMessage::query()

            ->where('conversation_id', $conversation->id)
            ->where('role', MessageRole::Visitor)
            ->whereIn('kind', [MessageKind::Text, MessageKind::Image])
            ->when($lastRepliedSeqNo !== null, fn ($query) => $query->where('seq_no', '>', $lastRepliedSeqNo))
            ->orderByDesc('seq_no')
            ->limit(self::MAX_MESSAGES)
            ->get()
            ->reverse()
            ->values()
            ->all();
    }
}
