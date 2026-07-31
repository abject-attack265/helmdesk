<?php

namespace App\Actions\Reception;

use App\Jobs\Reception\RunReceptionTurnJob;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * 建立 AI turn 活动租约并派发接待轮次任务。
 */
class DispatchReceptionTurnAction
{
    use AsAction;

    /**
     * 注入接待实时活动通知服务。
     */
    public function __construct(
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
    ) {}

    /**
     * 派发接待轮次，并在入队失败时释放活动租约。
     *
     * @param  list<string>  $messageIds
     * @param  list<string>  $mediaMessageIds
     */
    public function handle(
        string $conversationId,
        string $aggregatedText,
        array $messageIds,
        array $mediaMessageIds,
    ): void {
        $activityId = (string) Str::uuid();
        $this->realtimeNotifier->aiTurnQueued($conversationId, $activityId);

        try {
            RunReceptionTurnJob::dispatch(
                $conversationId,
                $aggregatedText,
                $messageIds,
                $mediaMessageIds,
                $activityId,
            );
        } catch (Throwable $exception) {
            Log::warning('[reception] AI 接待轮次入队失败', [
                'conversation_id' => $conversationId,
                'activity_id' => $activityId,
                'text_message_count' => count($messageIds),
                'media_message_count' => count($mediaMessageIds),
                'exception_class' => $exception::class,
                'exception' => $exception->getMessage(),
            ]);
            $this->realtimeNotifier->aiTurnStopped($conversationId, $activityId);

            throw $exception;
        }
    }
}
