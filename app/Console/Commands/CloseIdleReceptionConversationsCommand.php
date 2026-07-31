<?php

namespace App\Console\Commands;

use App\Actions\Reception\CloseIdleReceptionConversationsAction;
use Illuminate\Console\Command;

/**
 * 定时关闭超过接待方案「空闲自动结束」时长仍无新消息的会话（不区分接待状态一律硬关单）。
 */
class CloseIdleReceptionConversationsCommand extends Command
{
    /** @var string 命令名称和参数签名。 */
    protected $signature = 'reception:close-idle-conversations';

    /** @var string 命令说明。 */
    protected $description = 'Close reception conversations idle beyond their plan auto-close threshold.';

    /**
     * 执行空闲会话自动关闭任务。
     */
    public function handle(CloseIdleReceptionConversationsAction $action): int
    {
        $result = $action->handle();

        $this->components->info(sprintf('Auto-closed idle conversations: %d', $result['closed']));

        return self::SUCCESS;
    }
}
