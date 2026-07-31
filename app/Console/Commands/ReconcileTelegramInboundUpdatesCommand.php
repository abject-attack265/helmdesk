<?php

namespace App\Console\Commands;

use App\Actions\Channel\Telegram\ReconcileTelegramInboundUpdatesAction;
use Illuminate\Console\Command;

/** 对账 Telegram 入站 Update 处理台账。 */
class ReconcileTelegramInboundUpdatesCommand extends Command
{
    protected $signature = 'telegram-inbound:reconcile {--include-failed : 重新派发失败记录}';

    protected $description = '重新派发到期、租约超时或指定重放的 Telegram 入站 Update';

    /** 执行 Telegram 入站 Update 对账。 */
    public function handle(ReconcileTelegramInboundUpdatesAction $action): int
    {
        $count = $action->handle((bool) $this->option('include-failed'));
        $this->info("已派发 {$count} 条 Telegram 入站 Update。");

        return self::SUCCESS;
    }
}
