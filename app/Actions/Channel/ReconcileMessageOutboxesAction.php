<?php

namespace App\Actions\Channel;

use App\Enums\ChannelType;
use App\Enums\MessageOutboxStatus;
use App\Jobs\Telegram\SendTelegramMessageJob;
use App\Jobs\WechatOfficialAccount\SendWechatOfficialAccountMessageJob;
use App\Models\MessageOutbox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/** 重新派发到期或租约超时的 Outbox。 */
class ReconcileMessageOutboxesAction
{
    use AsAction;

    public const int STUCK_AFTER_SECONDS = 180;

    private const int BATCH_LIMIT = 200;

    /** 重新派发到期或租约超时的 Outbox。 */
    public function handle(?ChannelType $channelType = null): int
    {
        $now = now();
        $stuckThreshold = $now->copy()->subSeconds(self::STUCK_AFTER_SECONDS);
        $nextReconcileAt = $now->copy()->addSeconds(self::STUCK_AFTER_SECONDS);

        $outboxes = MessageOutbox::query()
            ->whereIn('status', [MessageOutboxStatus::Pending, MessageOutboxStatus::Sending])
            ->when($channelType !== null, fn (Builder $query) => $query->where('channel_type', $channelType))
            ->where(fn (Builder $query) => $this->eligible($query, $now, $stuckThreshold))
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::BATCH_LIMIT)
            ->get();

        $count = 0;
        foreach ($outboxes as $outbox) {
            $claimed = MessageOutbox::query()
                ->whereKey($outbox->id)
                ->where(fn (Builder $query) => $this->eligible($query, $now, $stuckThreshold))
                ->update([
                    'status' => MessageOutboxStatus::Pending,
                    'locked_at' => null,
                    'lock_token' => null,
                    'available_at' => $nextReconcileAt,
                    'updated_at' => now(),
                ]);

            if ($claimed !== 1) {
                continue;
            }

            match ($outbox->channel_type) {
                ChannelType::Telegram => SendTelegramMessageJob::dispatch((string) $outbox->conversation_message_id),
                ChannelType::WechatOfficialAccount => SendWechatOfficialAccountMessageJob::dispatch((string) $outbox->conversation_message_id),
            };
            $count++;
        }

        if ($count > 0) {
            Log::info('Message Outbox 对账已重新派发投递任务。', [
                'channel_type' => $channelType?->value,
                'count' => $count,
            ]);
        }

        return $count;
    }

    /** 限定已到重试时间或发送租约超时的 Outbox。 */
    private function eligible(Builder $query, Carbon $now, Carbon $stuckThreshold): void
    {
        $query
            ->where(function (Builder $pending) use ($now): void {
                $pending->where('status', MessageOutboxStatus::Pending)->where('available_at', '<=', $now);
            })
            ->orWhere(function (Builder $sending) use ($stuckThreshold): void {
                $sending->where('status', MessageOutboxStatus::Sending)->where('locked_at', '<=', $stuckThreshold);
            });
    }
}
