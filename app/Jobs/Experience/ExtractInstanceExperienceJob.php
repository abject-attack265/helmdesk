<?php

namespace App\Jobs\Experience;

use App\Actions\Experience\ExtractExperienceCandidatesAction;
use App\Enums\ExperienceExtractionStatus;
use App\Enums\ReceptionLanguage;
use App\Models\ExperienceExtraction;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 管理员触发的经验提炼后台任务。
 *
 * 不重试：失败即把运行标记为 Failed 并广播，管理员在页面上看到失败原因后可重新触发；
 * 失败运行不计入「已提炼过」标记，所涉会话可重新勾选。
 */
class ExtractInstanceExperienceJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public readonly string $extractionId,
        public readonly ReceptionLanguage $language,
    ) {
        $this->queue = 'background';
    }

    /**
     * 执行提炼运行，候选按触发者界面语言（$language）书写。
     */
    public function handle(ExtractExperienceCandidatesAction $action): void
    {
        $extraction = ExperienceExtraction::query()->find($this->extractionId);
        if ($extraction === null || $extraction->status !== ExperienceExtractionStatus::Running) {
            return;
        }

        $action->handle($extraction, $this->language);
    }

    /**
     * 最终失败时把运行标记为 Failed 并广播，让页面上的进行中状态收敛。
     */
    public function failed(Throwable $exception): void
    {
        Log::warning('ExtractInstanceExperienceJob failed.', [
            'extraction_id' => $this->extractionId,
            'reason' => $exception->getMessage(),
        ]);

        $extraction = ExperienceExtraction::query()->find($this->extractionId);
        if ($extraction === null) {
            return;
        }

        $extraction->update([
            'status' => ExperienceExtractionStatus::Failed,
            'error' => $exception->getMessage(),
        ]);

        app(ReceptionRealtimeNotifier::class)->appChanged('experience_extraction_finished');
    }
}
