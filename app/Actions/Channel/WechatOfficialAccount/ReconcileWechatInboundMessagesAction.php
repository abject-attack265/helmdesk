<?php

namespace App\Actions\Channel\WechatOfficialAccount;

use App\Enums\WechatInboundMessageStatus;
use App\Jobs\WechatOfficialAccount\ProcessWechatOfficialAccountMessageJob;
use App\Models\WechatInboundMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/** 恢复到期、租约超时或明确要求重放的微信入站消息。 */
class ReconcileWechatInboundMessagesAction
{
    use AsAction;

    public const int STUCK_AFTER_SECONDS = 180;

    private const int BATCH_LIMIT = 200;

    /** 重新派发需要处理的微信入站台账。 */
    public function handle(bool $includeFailed = false): int
    {
        $now = now();
        $stuckBefore = $now->copy()->subSeconds(self::STUCK_AFTER_SECONDS);
        $nextReconcileAt = $now->copy()->addSeconds(self::STUCK_AFTER_SECONDS);
        $rows = WechatInboundMessage::query()
            ->where(fn (Builder $query) => $this->eligible($query, $now, $stuckBefore, $includeFailed))
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::BATCH_LIMIT)
            ->get();

        $count = 0;
        foreach ($rows as $row) {
            $reservationToken = (string) Str::uuid();
            $reset = WechatInboundMessage::query()
                ->whereKey($row->id)
                ->where(fn (Builder $query) => $this->eligible($query, $now, $stuckBefore, $includeFailed))
                ->update([
                    'status' => WechatInboundMessageStatus::Pending,
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

            ProcessWechatOfficialAccountMessageJob::dispatch((string) $row->id, $reservationToken);
            $count++;
        }

        if ($count > 0) {
            Log::info('微信公众号入站对账已重新派发处理任务。', [
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
                $pending->where('status', WechatInboundMessageStatus::Pending)
                    ->where(fn (Builder $due) => $due->whereNull('available_at')->orWhere('available_at', '<=', $now));
            })
            ->orWhere(function (Builder $processing) use ($stuckBefore): void {
                $processing->where('status', WechatInboundMessageStatus::Processing)
                    ->where('locked_at', '<=', $stuckBefore);
            });

        if ($includeFailed) {
            $query->orWhere('status', WechatInboundMessageStatus::Failed);
        }
    }
}
