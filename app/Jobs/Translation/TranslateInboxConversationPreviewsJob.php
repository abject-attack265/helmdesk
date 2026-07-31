<?php

namespace App\Jobs\Translation;

use App\Actions\Inbox\TranslateInboxConversationPreviewsAction;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 异步翻译收件箱会话列表预览并广播译文更新。
 */
class TranslateInboxConversationPreviewsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    /**
     * 创建会话列表预览翻译任务。
     *
     * @param  list<string>  $conversationIds
     */
    public function __construct(
        public readonly array $conversationIds,
        public readonly string $targetLocale,
        public readonly string $sourceLocale,
    ) {
        $this->queue = 'background';
    }

    /**
     * 翻译会话列表预览并发送实时通知，触发收件箱列表重新拉取。
     */
    public function handle(ReceptionRealtimeNotifier $notifier): void
    {
        $translated = TranslateInboxConversationPreviewsAction::run(
            conversationIds: $this->conversationIds,
            targetLocale: $this->targetLocale,
            sourceLocale: $this->sourceLocale,
        );
        if ($translated === 0) {
            return;
        }

        $notifier->appChanged('preview_translation_updated');
    }
}
