<?php

namespace App\Jobs\Translation;

use App\Actions\Inbox\TranslateInboxConversationSummariesAction;
use App\Models\Conversation;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * 异步翻译会话摘要并广播译文更新。
 */
class TranslateInboxConversationSummariesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    /**
     * 创建同一联系人的会话摘要翻译任务。
     *
     * @param  list<string>  $conversationIds
     */
    public function __construct(
        public readonly string $conversationId,
        public readonly array $conversationIds,
        public readonly string $targetLocale,
        public readonly string $sourceLocale,
        public readonly bool $force = false,
    ) {
        $this->queue = 'background';
    }

    /**
     * 翻译会话摘要并广播会话变更，触发收件箱时间线重新拉取。
     */
    public function handle(ReceptionRealtimeNotifier $notifier): void
    {
        $translated = TranslateInboxConversationSummariesAction::run(
            conversationId: $this->conversationId,
            conversationIds: $this->conversationIds,
            targetLocale: $this->targetLocale,
            sourceLocale: $this->sourceLocale,
            force: $this->force,
        );
        if ($translated === 0) {
            return;
        }

        $anchor = Conversation::query()
            ->find($this->conversationId);
        if ($anchor === null) {
            Log::warning('会话摘要翻译完成但锚点会话不存在，无法广播译文更新', [
                'conversation_id' => $this->conversationId,
                'translated_count' => $translated,
            ]);

            return;
        }

        $notifier->conversationChanged($anchor, 'summary_translation_updated');
    }
}
