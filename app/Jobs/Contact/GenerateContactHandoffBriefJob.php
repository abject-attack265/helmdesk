<?php

namespace App\Jobs\Contact;

use App\Actions\Contact\GenerateContactHandoffBriefAction;
use App\Models\Contact;
use App\Models\Conversation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 联系人接手简报生成队列任务，按联系人 ID 串行刷新 contacts.ai_context.handoff_brief。
 */
class GenerateContactHandoffBriefJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    /**
     * 创建联系人接手简报生成任务。
     */
    public function __construct(
        public readonly string $contactId,
        public readonly string $conversationId,
    ) {
        $this->queue = 'generate-contact-handoff-brief';
    }

    /**
     * 同一联系人串行执行，拿不到锁的任务释放回队列稍后重试。
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->contactId))
                ->releaseAfter(10)
                ->expireAfter(180),
        ];
    }

    /**
     * 执行联系人接手简报生成。
     */
    public function handle(GenerateContactHandoffBriefAction $action): void
    {
        $contact = Contact::query()->findOrFail($this->contactId);
        $conversation = Conversation::query()
            ->where('contact_id', $contact->id)
            ->findOrFail($this->conversationId);

        $action->handle($contact, $conversation);
    }

    /**
     * 记录联系人接手简报生成最终失败原因。
     */
    public function failed(Throwable $exception): void
    {
        Log::warning('GenerateContactHandoffBriefJob failed.', [
            'contact_id' => $this->contactId,
            'conversation_id' => $this->conversationId,
            'error_class' => $exception::class,
            'reason' => $exception->getMessage(),
        ]);
    }
}
