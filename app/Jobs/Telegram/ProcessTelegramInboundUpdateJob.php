<?php

namespace App\Jobs\Telegram;

use App\Actions\Channel\Telegram\ReceiveTelegramWebhookAction;
use App\Models\TelegramInboundUpdate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** 处理已持久化的 Telegram 入站 Update。 */
class ProcessTelegramInboundUpdateJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30];

    /** 创建 Telegram 入站 Update 任务。 */
    public function __construct(
        public readonly string $inboundUpdateId,
        public readonly ?string $reservationToken = null,
    ) {
        $this->queue = 'channel-inbound';
    }

    /** 领取入站台账并处理 Telegram Update。 */
    public function handle(ReceiveTelegramWebhookAction $receiver): void
    {
        $inbound = TelegramInboundUpdate::query()->find($this->inboundUpdateId);
        if ($inbound === null) {
            Log::warning('Telegram 入站任务找不到处理台账。', ['inbound_update_id' => $this->inboundUpdateId]);

            return;
        }

        $claimToken = $inbound->claimForProcessing($this->reservationToken);
        if ($claimToken === null) {
            return;
        }

        try {
            $receiver->handle($inbound);
            if (! $inbound->markProcessed($claimToken)) {
                Log::warning('Telegram 入站 Update 完成时已失去处理租约。', [
                    'inbound_update_id' => (string) $inbound->id,
                    'provider_update_id' => $inbound->provider_update_id,
                ]);
            }
        } catch (Throwable $exception) {
            $delay = $this->retryDelay($inbound->attempts);
            $released = $inbound->releaseForRetry($claimToken, $exception->getMessage(), $delay);
            Log::warning('Telegram 入站 Update 处理失败，等待队列重试。', [
                'inbound_update_id' => (string) $inbound->id,
                'provider_update_id' => $inbound->provider_update_id,
                'attempt' => $inbound->attempts,
                'retry_delay_seconds' => $delay,
                'lease_released' => $released,
                'exception' => $exception::class,
                'reason' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }

    /** 按当前处理次数返回下次重试等待秒数。 */
    private function retryDelay(int $attempts): int
    {
        return $attempts <= 1 ? 5 : 30;
    }

    /** 将耗尽重试的入站台账标记为失败。 */
    public function failed(Throwable $exception): void
    {
        $inbound = TelegramInboundUpdate::query()->find($this->inboundUpdateId);
        $inbound?->failIfUnprocessed($exception->getMessage());
        Log::warning('Telegram 入站任务已耗尽重试。', [
            'inbound_update_id' => $this->inboundUpdateId,
            'provider_update_id' => $inbound?->provider_update_id,
            'exception' => $exception::class,
            'reason' => $exception->getMessage(),
        ]);
    }
}
