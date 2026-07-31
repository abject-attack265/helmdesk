package runtime

import (
	"fmt"
	"log/slog"
	"os"
	"path/filepath"

	"helmdesk/internal/localization"
	"helmdesk/internal/runtime/sqlitevec"
	"helmdesk/internal/runtimeconfig"

	"github.com/dunglas/frankenphp"
)

// RunArtisan 在内嵌 PHP 运行时中执行 Artisan 命令并返回退出码。
func RunArtisan(arguments []string, options runtimeconfig.Config) int {
	cfg, err := newConfig(options)
	if err != nil {
		slog.Error(localization.Text("failed to load Artisan runtime configuration"), "error", err)
		return 1
	}
	if err := sqlitevec.Register(cfg.projectRoot); err != nil {
		slog.Error(localization.Text("failed to load sqlite-vec"), "error", err)
		return 1
	}
	return runCLI(cfg, arguments)
}

// SnapshotDatabases 使用内嵌 PHP 的 SQLite 连接生成业务分库一致性快照。
func SnapshotDatabases(options runtimeconfig.Config, mainDestination, ragDestination string) error {
	exitCode := RunArtisan(
		[]string{"helmdesk:snapshot-sqlite", mainDestination, ragDestination},
		options,
	)
	if exitCode != 0 {
		return fmt.Errorf(localization.Text("SQLite snapshot command exited with code %d"), exitCode)
	}
	return nil
}

// PrepareRestoredInstance 迁移恢复后的分库并同步重建全文检索索引。
func PrepareRestoredInstance(options runtimeconfig.Config) error {
	exitCode := RunArtisan([]string{"helmdesk:prepare-restored-instance"}, options)
	if exitCode != 0 {
		return fmt.Errorf(localization.Text("restore initialization command exited with code %d"), exitCode)
	}
	return nil
}

// runCLI 将参数传递给 Laravel Artisan 入口并返回退出码。
func runCLI(cfg *config, arguments []string) int {
	for key, value := range cfg.phpEnv {
		if err := os.Setenv(key, value); err != nil {
			slog.Error(localization.Text("failed to set Artisan environment variable"), "key", key, "error", err)
			return 1
		}
	}
	artisan := filepath.Join(cfg.projectRoot, "artisan")
	return frankenphp.ExecuteScriptCLI(artisan, append([]string{"artisan"}, arguments...))
}
