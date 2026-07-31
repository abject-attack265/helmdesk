package deployment

import (
	"path/filepath"
	"time"

	"helmdesk/internal/backup"
	"helmdesk/internal/runtime"
	"helmdesk/internal/runtimeconfig"
)

// createUpgradeBackup 创建带目标版本和时间标识的升级前备份。
func createUpgradeBackup(config runtimeconfig.Config, targetVersion string) (string, error) {
	now := time.Now().UTC()
	outputPath := filepath.Join(
		config.StoragePath,
		"backups",
		"pre-upgrade-"+targetVersion+"-"+now.Format("20060102T150405Z")+".tar.gz",
	)
	result, err := backup.Create(backup.CreateOptions{
		StoragePath: config.StoragePath,
		OutputPath:  outputPath,
		Now:         func() time.Time { return now },
		SnapshotDatabases: func(mainDestination, ragDestination string) error {
			return runtime.SnapshotDatabases(config, mainDestination, ragDestination)
		},
	})
	if err != nil {
		return "", err
	}
	return result.Path, nil
}

// restoreUpgradeBackup 恢复升级前备份并初始化运行数据。
func restoreUpgradeBackup(config runtimeconfig.Config, archivePath string) error {
	_, err := backup.Restore(backup.RestoreOptions{
		StoragePath: config.StoragePath,
		ArchivePath: archivePath,
		SnapshotDatabases: func(mainDestination, ragDestination string) error {
			return runtime.SnapshotDatabases(config, mainDestination, ragDestination)
		},
		PrepareInstance: func() error {
			return runtime.PrepareRestoredInstance(config)
		},
	})
	return err
}
