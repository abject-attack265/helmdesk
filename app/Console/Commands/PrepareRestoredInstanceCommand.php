<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\ConversationMessage;
use App\Models\KnowledgeNode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use RuntimeException;

/**
 * 为恢复后的业务数据创建瞬态分库并重建全文检索索引。
 */
class PrepareRestoredInstanceCommand extends Command
{
    protected $signature = 'helmdesk:prepare-restored-instance';

    protected $description = '初始化恢复后的 HelmDesk 瞬态数据';

    protected $hidden = true;

    /**
     * 执行数据库迁移并同步重建各模型的 TNTSearch 索引。
     */
    public function handle(): int
    {
        $this->components->info('正在迁移恢复后的数据库。');
        $this->executeArtisan('migrate', ['--force' => true, '--no-interaction' => true]);

        Config::set('scout.queue', false);
        foreach ([Contact::class, ConversationMessage::class, KnowledgeNode::class] as $model) {
            $this->components->info("正在重建检索索引：{$model}");
            $this->executeArtisan('scout:import', [
                'model' => $model,
                '--fresh' => true,
                '--no-interaction' => true,
            ]);
        }

        return self::SUCCESS;
    }

    /**
     * 执行内部 Artisan 命令并要求成功退出。
     *
     * @param  array<string, mixed>  $arguments
     */
    private function executeArtisan(string $command, array $arguments): void
    {
        $exitCode = Artisan::call($command, $arguments, $this->output);
        if ($exitCode !== self::SUCCESS) {
            throw new RuntimeException("Artisan 命令 {$command} 执行失败，退出码：{$exitCode}");
        }
    }
}
