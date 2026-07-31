<?php

namespace App\Actions\Conversation;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationThread;
use Illuminate\Support\Facades\Log;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 在联系人合并时归并双方同渠道线程并重算当前会话投影。
 */
class MergeContactConversationThreadsAction
{
    use AsAction;

    /**
     * 将被合并联系人的线程归并到目标联系人。
     */
    public function handle(Contact $target, Contact $merged): void
    {
        $threads = ConversationThread::query()
            ->whereIn('contact_id', [$target->id, $merged->id])
            ->orderBy('channel_id')
            ->orderBy('contact_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $targetThreads = $threads
            ->where('contact_id', $target->id)
            ->keyBy('channel_id');
        $sourceThreads = $threads->where('contact_id', $merged->id);

        foreach ($sourceThreads as $sourceThread) {
            $targetThread = $targetThreads->get($sourceThread->channel_id);

            if (! $targetThread instanceof ConversationThread) {
                $sourceThread->update(['contact_id' => $target->id]);

                continue;
            }

            $this->mergeThreadPair($target, $merged, $targetThread, $sourceThread);
        }

        SyncContactConversationThreadImportanceAction::run($target);
    }

    /**
     * 合并双方在同一渠道上的线程并重算当前代表会话。
     */
    private function mergeThreadPair(
        Contact $target,
        Contact $merged,
        ConversationThread $targetThread,
        ConversationThread $sourceThread,
    ): void {
        $representative = ConversationThread::representativeConversation(
            Conversation::query()
                ->where('channel_id', $targetThread->channel_id)
                ->whereIn('contact_id', [$target->id, $merged->id]),
        );

        if ($representative === null) {
            Log::warning('联系人合并线程缺少代表会话', [
                'target_contact_id' => (string) $target->id,
                'merged_contact_id' => (string) $merged->id,
                'channel_id' => (string) $targetThread->channel_id,
                'target_thread_id' => (string) $targetThread->id,
                'source_thread_id' => (string) $sourceThread->id,
            ]);

            throw new LogicException('联系人合并线程缺少代表会话。');
        }

        $sourceThread->delete();
        $targetThread->update(ConversationThread::projectionFromConversation($representative));
    }
}
