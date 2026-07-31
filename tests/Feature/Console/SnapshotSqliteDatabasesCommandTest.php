<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

test('业务分库快照包含各自连接的已提交数据', function () {
    DB::connection('sqlite')->statement('CREATE TABLE backup_main_marker (value TEXT NOT NULL)');
    DB::connection('sqlite')->table('backup_main_marker')->insert(['value' => 'main']);
    DB::connection('sqlite_rag')->statement('CREATE TABLE backup_rag_marker (value TEXT NOT NULL)');
    DB::connection('sqlite_rag')->table('backup_rag_marker')->insert(['value' => 'rag']);

    $directory = sys_get_temp_dir().'/helmdesk-snapshot-'.Str::uuid();
    File::ensureDirectoryExists($directory);
    $main = $directory.'/main.sqlite';
    $rag = $directory.'/rag.sqlite';

    try {
        $this->artisan('helmdesk:snapshot-sqlite', ['main' => $main, 'rag' => $rag])
            ->assertSuccessful();

        $mainPdo = new PDO("sqlite:{$main}");
        $ragPdo = new PDO("sqlite:{$rag}");

        expect($mainPdo->query('SELECT value FROM backup_main_marker')->fetchColumn())->toBe('main')
            ->and($ragPdo->query('SELECT value FROM backup_rag_marker')->fetchColumn())->toBe('rag')
            ->and($mainPdo->query('PRAGMA integrity_check')->fetchColumn())->toBe('ok')
            ->and($ragPdo->query('PRAGMA integrity_check')->fetchColumn())->toBe('ok');
    } finally {
        DB::connection('sqlite')->statement('DROP TABLE IF EXISTS backup_main_marker');
        DB::connection('sqlite_rag')->statement('DROP TABLE IF EXISTS backup_rag_marker');
        File::deleteDirectory($directory);
    }
});
