<?php

namespace App\Jobs\Translation;

use App\Actions\Inbox\TranslateInboxContactHandoffBriefAction;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 异步翻译联系人接手简报并广播应用变更。
 */
class TranslateInboxContactHandoffBriefJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    /**
     * 创建联系人接手简报翻译任务。
     */
    public function __construct(
        public readonly string $contactId,
        public readonly string $targetLocale,
        public readonly bool $force = false,
    ) {
        $this->queue = 'translations';
    }

    /**
     * 翻译联系人接手简报并广播应用变更。
     */
    public function handle(ReceptionRealtimeNotifier $notifier): void
    {
        $translated = TranslateInboxContactHandoffBriefAction::run(
            contactId: $this->contactId,
            targetLocale: $this->targetLocale,
            force: $this->force,
        );
        if ($translated === 0) {
            return;
        }

        $notifier->appChanged('contact_handoff_brief_translation_updated', [
            'contact_id' => $this->contactId,
        ]);
    }

    /**
     * 记录联系人接手简报翻译最终失败原因。
     */
    public function failed(Throwable $exception): void
    {
        Log::warning('TranslateInboxContactHandoffBriefJob failed.', [
            'contact_id' => $this->contactId,
            'target_locale' => $this->targetLocale,
            'error_class' => $exception::class,
            'reason' => $exception->getMessage(),
        ]);
    }
}
