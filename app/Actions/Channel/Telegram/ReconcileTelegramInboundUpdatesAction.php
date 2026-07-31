<?php

namespace App\Actions\Channel\Telegram;

use App\Enums\TelegramInboundUpdateStatus;
use App\Jobs\Telegram\ProcessTelegramInboundUpdateJob;
use App\Models\TelegramInboundUpdate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/** 恢复到期、租约超时或明确要求重放的 Telegram 入站 Update。 */
class ReconcileTelegramInboundUpdatesAction
{
    use AsAction;

    public const int STUCK_AFTER_SECONDS = 180;

    private const int BATCH_LIMIT = 200;

    /** 重新派发需要处理的 Telegram 入站台账。 */
    public function handle(bool $includeFailed = false): int
    {
        $now = now();
        $stuckBefore = $now->copy()->subSeconds(self::STUCK_AFTER_SECONDS);
        $nextReconcileAt = $now->copy()->addSeconds(self::STUCK_AFTER_SECONDS);
        $rows = TelegramInboundUpdate::query()
            ->where(fn (Builder $query) => $this->eligible($query, $now, $stuckBefore, $includeFailed))
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::BATCH_LIMIT)
            ->get();

        $count = 0;
        foreach ($rows as $row) {
            $reservationToken = (string) Str::uuid();
            $reset = TelegramInboundUpdate::query()
                ->whereKey($row->id)
                ->where(fn (Builder $query) => $this->eligible($query, $now, $stuckBefore, $includeFailed))
                ->update([
                    'status' => TelegramInboundUpdateStatus::Pending,
                    'available_at' => $nextReconcileAt,
                    'locked_at' => null,
                    'lock_token' => $reservationToken,
                    'failed_at' => null,
                    'last_error' => null,
                    'updated_at' => $now,
                ]);
            if ($reset !== 1) {
                continue;
            }

            ProcessTelegramInboundUpdateJob::dispatch((string) $row->id, $reservationToken);
            $count++;
        }

        if ($count > 0) {
            Log::info('Telegram 入站对账已重新派发处理任务。', [
                'count' => $count,
                'included_failed' => $includeFailed,
            ]);
        }

        return $count;
    }

    /** 限定到期、租约超时以及可选的失败台账。 */
    private function eligible(Builder $query, Carbon $now, Carbon $stuckBefore, bool $includeFailed): void
    {
        $query
            ->where(function (Builder $pending) use ($now): void {
                $pending->where('status', TelegramInboundUpdateStatus::Pending)
                    ->where(fn (Builder $due) => $due->whereNull('available_at')->orWhere('available_at', '<=', $now));
            })
            ->orWhere(function (Builder $processing) use ($stuckBefore): void {
                $processing->where('status', TelegramInboundUpdateStatus::Processing)
                    ->where('locked_at', '<=', $stuckBefore);
            });

        if ($includeFailed) {
            $query->orWhere('status', TelegramInboundUpdateStatus::Failed);
        }
    }
}
