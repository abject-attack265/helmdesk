<?php

namespace App\Jobs\Conversation;

use App\Actions\CustomAttribute\GenerateContactAttributeValuesAction;
use App\Models\Conversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 联系人属性 AI 提取队列任务，按会话 ID 串行落 contact_attribute_values。
 */
class GenerateContactAttributeValuesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    /**
     * 创建联系人属性提取任务。
     */
    public function __construct(
        public readonly string $conversationId,
    ) {
        $this->queue = 'background';
    }

    /**
     * 同一会话串行执行，避免并发提取互相覆盖。
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('contact-attributes:'.$this->conversationId))
                ->releaseAfter(10)
                ->expireAfter(180),
        ];
    }

    /**
     * 执行联系人属性提取。
     */
    public function handle(GenerateContactAttributeValuesAction $action): void
    {
        $action->handle(Conversation::query()->findOrFail($this->conversationId));
    }

    /**
     * 记录属性提取最终失败原因。
     */
    public function failed(Throwable $exception): void
    {
        Log::warning('GenerateContactAttributeValuesJob failed.', [
            'conversation_id' => $this->conversationId,
            'reason' => $exception->getMessage(),
        ]);
    }
}
