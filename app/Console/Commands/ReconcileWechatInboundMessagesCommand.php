<?php

namespace App\Console\Commands;

use App\Actions\Channel\WechatOfficialAccount\ReconcileWechatInboundMessagesAction;
use Illuminate\Console\Command;

/** 恢复微信公众号未完成的入站消息处理。 */
class ReconcileWechatInboundMessagesCommand extends Command
{
    protected $signature = 'wechat-inbound:reconcile {--retry-failed : 同时重放最终失败的入站消息}';

    protected $description = '重新派发待处理、租约超时或指定重放的微信公众号入站消息';

    /** 执行微信入站消息对账。 */
    public function handle(ReconcileWechatInboundMessagesAction $action): int
    {
        $count = $action->handle((bool) $this->option('retry-failed'));
        $this->info("已重新派发 {$count} 条微信公众号入站消息。");

        return self::SUCCESS;
    }
}
