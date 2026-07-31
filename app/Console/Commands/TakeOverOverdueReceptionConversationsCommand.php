<?php

namespace App\Console\Commands;

use App\Actions\Reception\TakeOverOverdueReceptionConversationsAction;
use Illuminate\Console\Command;

/**
 * 定时把「排队无人接待超时」「坐席无响应超时」的会话交回 AI 接待并补答积压消息。
 * 调度上排在 reception:close-idle-conversations 之前，保证到期会话先接管后再谈关单。
 */
class TakeOverOverdueReceptionConversationsCommand extends Command
{
    /** @var string 命令名称和参数签名。 */
    protected $signature = 'reception:take-over-overdue-conversations';

    /** @var string 命令说明。 */
    protected $description = 'Hand overdue queued or unresponsive conversations back to AI reception.';

    /**
     * 执行超时会话 AI 接管任务。
     */
    public function handle(TakeOverOverdueReceptionConversationsAction $action): int
    {
        $result = $action->handle();

        $this->components->info(sprintf('Conversations taken over by AI: %d', $result['taken_over']));

        return self::SUCCESS;
    }
}
