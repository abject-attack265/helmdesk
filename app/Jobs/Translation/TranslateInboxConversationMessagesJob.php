<?php

namespace App\Jobs\Translation;

use App\Actions\Inbox\TranslateInboxConversationMessagesAction;
use App\Models\Conversation;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * 异步翻译收件箱消息并广播译文更新。
 */
class TranslateInboxConversationMessagesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    /**
     * 创建指定会话的消息翻译任务。
     *
     * @param  list<string>  $messageIds
     */
    public function __construct(
        public readonly string $conversationId,
        public readonly array $messageIds,
        public readonly string $targetLocale,
        public readonly string $sourceLocale,
        public readonly bool $force = false,
    ) {
        $this->queue = 'background';
    }

    /**
     * 翻译消息并广播会话变更，触发收件箱重新拉取。
     */
    public function handle(ReceptionRealtimeNotifier $notifier): void
    {
        $translated = TranslateInboxConversationMessagesAction::run(
            conversationId: $this->conversationId,
            messageIds: $this->messageIds,
            targetLocale: $this->targetLocale,
            sourceLocale: $this->sourceLocale,
            force: $this->force,
        );
        if ($translated === 0) {
            return;
        }

        $conversation = Conversation::query()
            ->find($this->conversationId);
        if ($conversation === null) {
            Log::warning('消息翻译完成但会话不存在，无法广播译文更新', [
                'conversation_id' => $this->conversationId,
                'translated_count' => $translated,
            ]);

            return;
        }

        $notifier->conversationChanged($conversation, 'message_translation_updated');
    }
}
