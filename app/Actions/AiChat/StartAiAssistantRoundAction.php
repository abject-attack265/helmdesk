<?php

namespace App\Actions\AiChat;

use App\Data\AiChat\AiAssistantRoundData;
use App\Enums\AiAssistantMessageRole;
use App\Enums\AiAssistantMessageStatus;
use App\Models\AiAssistantThread;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 在指定 AI 助手线程中幂等创建一轮客服提问和待生成回答。
 */
class StartAiAssistantRoundAction
{
    use AsAction;

    /**
     * 锁定客户会话，复用指定线程或按需创建线程，并按 round ID 创建一对问答消息。
     *
     * @param  list<string>  $attachmentIds
     */
    public function handle(
        Conversation $conversation,
        User $user,
        string $roundId,
        string $content,
        array $attachmentIds = [],
        ?string $threadId = null,
    ): AiAssistantRoundData {
        return DB::transaction(function () use ($conversation, $user, $roundId, $content, $attachmentIds, $threadId): AiAssistantRoundData {
            $lockedConversation = Conversation::query()
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->first();

            if ($lockedConversation === null) {
                throw new NotFoundHttpException;
            }

            $thread = $threadId !== null
                ? AiAssistantThread::query()
                    ->forContactContext($lockedConversation)
                    ->whereKey($threadId)
                    ->first()
                : null;

            if ($threadId !== null && $thread === null) {
                throw new NotFoundHttpException;
            }

            $thread ??= AiAssistantThread::query()->create([
                'conversation_id' => $lockedConversation->id,
            ]);

            $userMessage = $thread->messages()->firstOrCreate(
                [
                    'round_id' => $roundId,
                    'role' => AiAssistantMessageRole::User,
                ],
                [
                    'content' => $content !== '' ? $content : null,
                    'attachment_ids' => $attachmentIds !== [] ? array_values($attachmentIds) : null,
                    'sender_user_id' => $user->id,
                    'status' => AiAssistantMessageStatus::Completed,
                ],
            );

            $assistantMessage = $thread->messages()->firstOrCreate(
                [
                    'round_id' => $roundId,
                    'role' => AiAssistantMessageRole::Assistant,
                ],
                [
                    'content' => null,
                    'sender_user_id' => null,
                    'status' => AiAssistantMessageStatus::Pending,
                ],
            );

            return new AiAssistantRoundData(
                thread_id: $thread->id,
                user_message_id: $userMessage->id,
                assistant_message_id: $assistantMessage->id,
            );
        });
    }
}
