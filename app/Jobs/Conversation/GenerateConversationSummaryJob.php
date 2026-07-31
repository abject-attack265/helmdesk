<?php

namespace App\Jobs\Conversation;

use App\Actions\Conversation\GenerateConversationSummaryAction;
use App\Models\Conversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 会话 AI 摘要生成队列任务，按会话 ID 串行滚动 conversations.summary。
 */
class GenerateConversationSummaryJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    /**
     * 创建会话摘要生成任务。
     */
    public function __construct(
        public readonly string $conversationId,
        public readonly bool $force = false,
    ) {
        $this->queue = 'background';
    }

    /**
     * 同一会话串行执行，拿不到锁的任务释放回队列稍后重试。
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->conversationId))
                ->releaseAfter(10)
                ->expireAfter(180),
        ];
    }

    /**
     * 执行会话摘要生成。
     */
    public function handle(GenerateConversationSummaryAction $action): void
    {
        $action->handle(Conversation::query()->findOrFail($this->conversationId), $this->force);
    }

    /**
     * 记录摘要生成最终失败原因。
     */
    public function failed(Throwable $exception): void
    {
        Log::warning('GenerateConversationSummaryJob failed.', [
            'conversation_id' => $this->conversationId,
            'reason' => $exception->getMessage(),
        ]);
    }
}
