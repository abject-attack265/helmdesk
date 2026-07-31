<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;
use Symfony\Component\Filesystem\Path;

/**
 * 使用 SQLite VACUUM INTO 为业务分库生成在线一致性快照。
 */
class SnapshotSqliteDatabasesCommand extends Command
{
    protected $signature = 'helmdesk:snapshot-sqlite {main} {rag}';

    protected $description = '为 HelmDesk 业务分库生成一致性快照';

    protected $hidden = true;

    /**
     * 依次生成主业务库和知识检索库快照。
     */
    public function handle(): int
    {
        $this->components->info('正在创建主业务库快照。');
        $this->snapshot('sqlite', (string) $this->argument('main'));
        $this->components->info('正在创建知识检索库快照。');
        $this->snapshot('sqlite_rag', (string) $this->argument('rag'));

        return self::SUCCESS;
    }

    /**
     * 将指定连接写入不存在的目标文件。
     */
    private function snapshot(string $connection, string $destination): void
    {
        if (! Path::isAbsolute($destination)) {
            throw new RuntimeException('SQLite 快照目标必须是绝对路径');
        }
        if (file_exists($destination)) {
            throw new RuntimeException("SQLite 快照目标已存在：{$destination}");
        }

        $pdo = DB::connection($connection)->getPdo();
        $pdo->exec('VACUUM INTO '.$pdo->quote($destination, PDO::PARAM_STR));
    }
}
