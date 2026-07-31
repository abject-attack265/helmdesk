<?php

namespace App\Console\Commands;

use App\Actions\User\OfflineInactiveInstanceMembersAction;
use Illuminate\Console\Command;

/**
 * 定时把超过不活跃阈值仍标记在线的客服自动置为离线。
 */
class OfflineInactiveTeammatesCommand extends Command
{
    /** @var string 命令名称和参数签名。 */
    protected $signature = 'teammates:offline-inactive';

    /** @var string 命令说明。 */
    protected $description = 'Mark app members offline when inactive beyond the threshold.';

    /**
     * 执行不活跃成员自动离线任务。
     */
    public function handle(OfflineInactiveInstanceMembersAction $action): int
    {
        $offlined = $action->handle();

        $this->components->info(sprintf('Auto-offlined inactive members: %d', $offlined));

        return self::SUCCESS;
    }
}
