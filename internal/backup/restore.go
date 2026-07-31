package backup

import (
	"errors"
	"fmt"
	"io/fs"
	"log/slog"
	"os"
	"path/filepath"
	"time"

	"helmdesk/internal/localization"
	"helmdesk/internal/storageguard"
)

var transientArtifacts = []string{
	"database/cache.sqlite",
	"database/cache.sqlite-wal",
	"database/cache.sqlite-shm",
	"database/cache.sqlite-journal",
	"database/session.sqlite",
	"database/session.sqlite-wal",
	"database/session.sqlite-shm",
	"database/session.sqlite-journal",
	"database/jobs.sqlite",
	"database/jobs.sqlite-wal",
	"database/jobs.sqlite-shm",
	"database/jobs.sqlite-journal",
	"framework/cache/data",
	"framework/cache/opcache",
	"framework/sessions",
	"framework/views",
	"framework/workers",
	"scout",
}

var replacedArtifacts = []string{
	".env",
	"database/main.sqlite",
	"database/main.sqlite-wal",
	"database/main.sqlite-shm",
	"database/main.sqlite-journal",
	"database/rag.sqlite",
	"database/rag.sqlite-wal",
	"database/rag.sqlite-shm",
	"database/rag.sqlite-journal",
	"app/private",
	"app/public",
}

// ErrServiceRunning 表示恢复目标仍被 HelmDesk 运行时占用。
var ErrServiceRunning = localization.New("restore requires the HelmDesk service to be completely stopped")

// Restore 校验备份、创建恢复前安全快照并离线替换业务数据。
func Restore(options RestoreOptions) (RestoreResult, error) {
	storagePath, err := normalizeStoragePath(options.StoragePath)
	if err != nil {
		return RestoreResult{}, err
	}
	if options.ArchivePath == "" {
		return RestoreResult{}, errors.New(localization.Text("backup file cannot be empty"))
	}
	archivePath, err := filepath.Abs(options.ArchivePath)
	if err != nil {
		return RestoreResult{}, err
	}
	if options.SnapshotDatabases == nil {
		return RestoreResult{}, errors.New(localization.Text("database snapshot implementation cannot be empty"))
	}
	if options.PrepareInstance == nil {
		return RestoreResult{}, errors.New(localization.Text("restore initialization implementation cannot be empty"))
	}
	lock, err := storageguard.Acquire(storagePath)
	if err != nil {
		if errors.Is(err, storageguard.ErrInUse) {
			return RestoreResult{}, ErrServiceRunning
		}
		return RestoreResult{}, err
	}
	defer func() {
		if err := lock.Close(); err != nil {
			slog.Warn(localization.Text("failed to release runtime directory lock"), "storage_path", storagePath, "error", err)
		}
	}()

	stagePath, err := os.MkdirTemp(filepath.Dir(storagePath), ".helmdesk-restore-*")
	if err != nil {
		return RestoreResult{}, err
	}
	defer func() {
		if err := os.RemoveAll(stagePath); err != nil {
			slog.Warn(localization.Text("failed to clean up restore staging directory"), "path", stagePath, "error", err)
		}
	}()
	slog.Info(localization.Text("validating backup archive"), "archive_path", archivePath)
	if _, err := extractArchive(archivePath, stagePath); err != nil {
		return RestoreResult{}, fmt.Errorf(localization.Text("backup validation failed: %w"), err)
	}
	slog.Info(localization.Text("backup archive validation passed"), "archive_path", archivePath)

	createdAt := currentTime(options.Now)
	safetyBackupPath, err := createSafetyBackup(storagePath, createdAt, options.SnapshotDatabases)
	if err != nil {
		return RestoreResult{}, err
	}
	result := RestoreResult{ArchivePath: archivePath, SafetyBackupPath: safetyBackupPath}
	if safetyBackupPath == "" {
		slog.Info(localization.Text("target runtime directory has no business data; skipping pre-restore safety backup"), "storage_path", storagePath)
	} else {
		slog.Info(localization.Text("pre-restore safety backup created"), "path", safetyBackupPath)
	}
	if err := matchTreeOwnership(stagePath, storagePath); err != nil {
		return result, fmt.Errorf(localization.Text("failed to set restored data ownership: %w"), err)
	}
	slog.Info(localization.Text("replacing business data"), "storage_path", storagePath)
	if err := applyRestore(storagePath, stagePath); err != nil {
		return result, err
	}
	slog.Info(localization.Text("business data replacement completed; initializing databases and search indexes"))
	if err := options.PrepareInstance(); err != nil {
		return result, fmt.Errorf(localization.Text("business data was restored, but transient data initialization failed: %w"), err)
	}
	if err := matchRestoredOwnership(storagePath); err != nil {
		return result, fmt.Errorf(localization.Text("business data was restored, but setting runtime data ownership failed: %w"), err)
	}
	slog.Info(localization.Text("backup restore completed"), "storage_path", storagePath)
	return result, nil
}

// matchRestoredOwnership 让恢复和初始化生成的数据继承运行目录所有者。
func matchRestoredOwnership(storagePath string) error {
	artifacts := make([]string, 0, len(replacedArtifacts)+len(transientArtifacts))
	artifacts = append(artifacts, replacedArtifacts...)
	artifacts = append(artifacts, transientArtifacts...)
	for _, relative := range artifacts {
		if err := matchTreeOwnership(
			filepath.Join(storagePath, filepath.FromSlash(relative)),
			storagePath,
		); err != nil {
			return err
		}
	}
	return nil
}

// createSafetyBackup 为完整的目标实例生成恢复前快照。
func createSafetyBackup(storagePath string, createdAt time.Time, snapshot SnapshotDatabasesFunc) (string, error) {
	required, err := safetyBackupRequired(storagePath)
	if err != nil {
		return "", err
	}
	if !required {
		return "", nil
	}
	outputPath := filepath.Join(
		storagePath,
		"backups",
		fmt.Sprintf("pre-restore-%s.tar.gz", createdAt.Format("20060102T150405Z")),
	)
	result, err := Create(CreateOptions{
		StoragePath:       storagePath,
		OutputPath:        outputPath,
		Now:               func() time.Time { return createdAt },
		SnapshotDatabases: snapshot,
	})
	if err != nil {
		return "", fmt.Errorf(localization.Text("failed to create the pre-restore safety backup: %w"), err)
	}
	return result.Path, nil
}

// safetyBackupRequired 在完整实例上要求安全快照，并拒绝不完整的业务数据。
func safetyBackupRequired(storagePath string) (bool, error) {
	required := []string{".env", "database/main.sqlite", "database/rag.sqlite"}
	present := 0
	for _, relative := range required {
		_, statErr := os.Stat(filepath.Join(storagePath, filepath.FromSlash(relative)))
		switch {
		case statErr == nil:
			present++
		case errors.Is(statErr, os.ErrNotExist):
		default:
			return false, statErr
		}
	}
	if present == len(required) {
		return true, nil
	}
	if present > 0 {
		return false, errors.New(localization.Text("target runtime directory contains incomplete business data; refusing to overwrite it"))
	}
	hasFiles, err := treeContainsFiles(filepath.Join(storagePath, "app"))
	if err != nil {
		return false, err
	}
	if hasFiles {
		return false, errors.New(localization.Text("target runtime directory contains incomplete business data; refusing to overwrite it"))
	}
	return false, nil
}

// treeContainsFiles 判断目录树中是否存在非目录条目。
func treeContainsFiles(root string) (bool, error) {
	if _, err := os.Lstat(root); errors.Is(err, os.ErrNotExist) {
		return false, nil
	} else if err != nil {
		return false, err
	}
	found := false
	err := filepath.WalkDir(root, func(path string, entry fs.DirEntry, walkErr error) error {
		if walkErr != nil {
			return walkErr
		}
		if !entry.IsDir() {
			found = true
			return fs.SkipAll
		}
		return nil
	})
	return found, err
}

type artifactMove struct {
	source      string
	destination string
}

// applyRestore 通过同文件系统重命名替换业务数据并清空瞬态数据。
func applyRestore(storagePath, stagePath string) error {
	rollbackPath, err := os.MkdirTemp(storagePath, ".restore-rollback-*")
	if err != nil {
		return err
	}
	rollbackMoves := make([]artifactMove, 0, len(replacedArtifacts)+len(transientArtifacts))
	artifacts := make([]string, 0, len(replacedArtifacts)+len(transientArtifacts))
	artifacts = append(artifacts, replacedArtifacts...)
	artifacts = append(artifacts, transientArtifacts...)
	for _, relative := range artifacts {
		target := filepath.Join(storagePath, filepath.FromSlash(relative))
		rollback := filepath.Join(rollbackPath, filepath.FromSlash(relative))
		if err := moveIfExists(target, rollback); err != nil {
			rollbackErr := rollbackRestore(nil, rollbackMoves)
			return errors.Join(fmt.Errorf(localization.Text("failed to stage existing runtime data: %w"), err), rollbackErr)
		}
		if _, err := os.Lstat(rollback); err == nil {
			rollbackMoves = append(rollbackMoves, artifactMove{source: rollback, destination: target})
		}
	}

	replacements := []artifactMove{
		{source: filepath.Join(stagePath, "runtime", ".env"), destination: filepath.Join(storagePath, ".env")},
		{source: filepath.Join(stagePath, "database", "main.sqlite"), destination: filepath.Join(storagePath, "database", "main.sqlite")},
		{source: filepath.Join(stagePath, "database", "rag.sqlite"), destination: filepath.Join(storagePath, "database", "rag.sqlite")},
		{source: filepath.Join(stagePath, "files", "private"), destination: filepath.Join(storagePath, "app", "private")},
		{source: filepath.Join(stagePath, "files", "public"), destination: filepath.Join(storagePath, "app", "public")},
	}
	installed := make([]string, 0, len(replacements))
	for _, replacement := range replacements {
		if err := os.MkdirAll(filepath.Dir(replacement.destination), 0755); err != nil {
			rollbackErr := rollbackRestore(installed, rollbackMoves)
			return errors.Join(err, rollbackErr)
		}
		if err := os.Rename(replacement.source, replacement.destination); err != nil {
			rollbackErr := rollbackRestore(installed, rollbackMoves)
			return errors.Join(fmt.Errorf(localization.Text("failed to write restored data: %w"), err), rollbackErr)
		}
		installed = append(installed, replacement.destination)
	}
	if err := os.RemoveAll(rollbackPath); err != nil {
		slog.Warn(localization.Text("failed to clean up restore rollback directory"), "path", rollbackPath, "error", err)
	}
	return nil
}

func moveIfExists(source, destination string) error {
	if _, err := os.Lstat(source); errors.Is(err, os.ErrNotExist) {
		return nil
	} else if err != nil {
		return err
	}
	if err := os.MkdirAll(filepath.Dir(destination), 0700); err != nil {
		return err
	}
	return os.Rename(source, destination)
}

// rollbackRestore 删除已安装条目并恢复原始运行数据。
func rollbackRestore(installed []string, moves []artifactMove) error {
	var rollbackErrs []error
	for index := len(installed) - 1; index >= 0; index-- {
		if err := os.RemoveAll(installed[index]); err != nil {
			rollbackErrs = append(rollbackErrs, fmt.Errorf(localization.Text("failed to remove incomplete restored data %s: %w"), installed[index], err))
		}
	}
	for index := len(moves) - 1; index >= 0; index-- {
		move := moves[index]
		if err := os.MkdirAll(filepath.Dir(move.destination), 0755); err != nil {
			rollbackErrs = append(rollbackErrs, fmt.Errorf(localization.Text("failed to create restore rollback directory %s: %w"), move.destination, err))
			continue
		}
		if err := os.Rename(move.source, move.destination); err != nil {
			rollbackErrs = append(rollbackErrs, fmt.Errorf(localization.Text("failed to restore original runtime data %s: %w"), move.destination, err))
		}
	}
	return errors.Join(rollbackErrs...)
}
