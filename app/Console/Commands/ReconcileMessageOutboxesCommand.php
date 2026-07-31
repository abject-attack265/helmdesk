<?php

namespace App\Console\Commands;

use App\Actions\Channel\ReconcileMessageOutboxesAction;
use App\Enums\ChannelType;
use Illuminate\Console\Command;

/** 恢复外部渠道丢失或卡住的出站投递任务。 */
class ReconcileMessageOutboxesCommand extends Command
{
    protected $signature = 'outbox:reconcile {--channel= : 限定渠道类型}';

    protected $description = 'Re-dispatch pending or stuck external channel message outboxes.';

    public function handle(ReconcileMessageOutboxesAction $action): int
    {
        $channel = $this->option('channel');
        $channelType = is_string($channel) && $channel !== '' ? ChannelType::tryFrom($channel) : null;
        if ($channel !== null && $channel !== '' && $channelType === null) {
            $this->error('Unsupported channel type.');

            return self::FAILURE;
        }

        $count = $action->handle($channelType);
        $this->info("Re-dispatched {$count} external outbound message(s).");

        return self::SUCCESS;
    }
}
