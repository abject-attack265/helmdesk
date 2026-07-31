<?php

namespace App\Jobs\Conversation;

use App\Actions\Conversation\GenerateConversationTagsAction;
use App\Models\Conversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 会话 AI 打标签队列任务，按会话 ID 串行落 conversation_tag_assignments。
 */
class GenerateConversationTagsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    /**
     * 创建会话打标签任务。
     */
    public function __construct(
        public readonly string $conversationId,
    ) {
        $this->queue = 'background';
    }

    /**
     * 同一会话串行执行，避免并发打标签互相覆盖。
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('conversation-tags:'.$this->conversationId))
                ->releaseAfter(10)
                ->expireAfter(180),
        ];
    }

    /**
     * 执行会话打标签。
     */
    public function handle(GenerateConversationTagsAction $action): void
    {
        $action->handle(Conversation::query()->findOrFail($this->conversationId));
    }

    /**
     * 记录打标签最终失败原因。
     */
    public function failed(Throwable $exception): void
    {
        Log::warning('GenerateConversationTagsJob failed.', [
            'conversation_id' => $this->conversationId,
            'reason' => $exception->getMessage(),
        ]);
    }
}
