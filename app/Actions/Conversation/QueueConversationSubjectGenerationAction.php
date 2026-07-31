<?php

namespace App\Actions\Conversation;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Jobs\Conversation\GenerateConversationSubjectJob;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 为无主题会话排队生成主题。
 */
class QueueConversationSubjectGenerationAction
{
    use AsAction;

    /**
     * 为符合条件的访客文本消息派发事务提交后的主题生成任务。
     */
    public function handle(ConversationMessage $message): void
    {
        if (
            $message->role !== MessageRole::Visitor
            || $message->kind !== MessageKind::Text
            || blank($message->content)
        ) {
            return;
        }

        $subject = Conversation::query()
            ->whereKey($message->conversation_id)
            ->value('subject');

        if (filled($subject)) {
            return;
        }

        $conversationId = (string) $message->conversation_id;

        $message->getConnection()->afterCommit(static function () use ($conversationId): void {
            GenerateConversationSubjectJob::dispatch($conversationId)
                ->delay(now()->addSeconds(10));
        });
    }
}
