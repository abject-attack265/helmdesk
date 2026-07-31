package backup

import (
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"io/fs"
	"log/slog"
	"os"
	"path/filepath"
	"sort"
	"strings"
	"time"

	"helmdesk/internal/localization"
)

// Create 生成包含业务分库、运行密钥和本地业务文件的压缩备份。
func Create(options CreateOptions) (CreateResult, error) {
	storagePath, err := normalizeStoragePath(options.StoragePath)
	if err != nil {
		return CreateResult{}, err
	}
	if options.SnapshotDatabases == nil {
		return CreateResult{}, errors.New(localization.Text("database snapshot implementation cannot be empty"))
	}
	createdAt := currentTime(options.Now)
	outputPath, err := resolveOutputPath(storagePath, options.OutputPath, "helmdesk-backup", createdAt)
	if err != nil {
		return CreateResult{}, err
	}
	for _, businessTree := range []string{
		filepath.Join(storagePath, "app", "private"),
		filepath.Join(storagePath, "app", "public"),
	} {
		if pathWithin(outputPath, businessTree) {
			return CreateResult{}, errors.New(localization.Text("backup file cannot be written inside the local business file directory"))
		}
	}
	if err := requireBackupSources(storagePath); err != nil {
		return CreateResult{}, err
	}
	if err := os.MkdirAll(filepath.Dir(outputPath), 0755); err != nil {
		return CreateResult{}, err
	}
	if pathWithin(outputPath, filepath.Join(storagePath, "backups")) {
		if err := matchOwnership(filepath.Dir(outputPath), storagePath); err != nil {
			return CreateResult{}, err
		}
	}
	stagePath, err := os.MkdirTemp(filepath.Dir(outputPath), ".helmdesk-backup-*")
	if err != nil {
		return CreateResult{}, err
	}
	defer func() {
		if err := os.RemoveAll(stagePath); err != nil {
			slog.Warn(localization.Text("failed to clean up backup staging directory"), "path", stagePath, "error", err)
		}
	}()

	slog.Info(localization.Text("creating SQLite business database snapshots"), "storage_path", storagePath)
	if err := stageBackup(storagePath, stagePath, options.SnapshotDatabases); err != nil {
		return CreateResult{}, err
	}
	backupManifest, err := buildManifest(stagePath, createdAt)
	if err != nil {
		return CreateResult{}, err
	}
	manifestContents, err := json.MarshalIndent(backupManifest, "", "  ")
	if err != nil {
		return CreateResult{}, err
	}
	manifestContents = append(manifestContents, '\n')
	if err := os.WriteFile(filepath.Join(stagePath, "manifest.json"), manifestContents, 0600); err != nil {
		return CreateResult{}, err
	}
	slog.Info(localization.Text("writing backup archive"), "output_path", outputPath)
	if err := writeArchive(stagePath, outputPath, createdAt); err != nil {
		return CreateResult{}, err
	}
	if pathWithin(outputPath, storagePath) {
		if err := matchOwnership(outputPath, storagePath); err != nil {
			return CreateResult{}, err
		}
	}
	info, err := os.Stat(outputPath)
	if err != nil {
		return CreateResult{}, err
	}
	slog.Info(localization.Text("backup creation completed"), "output_path", outputPath, "size_bytes", info.Size())

	return CreateResult{Path: outputPath, Size: info.Size()}, nil
}

// normalizeStoragePath 返回绝对运行目录并拒绝文件系统根目录。
func normalizeStoragePath(value string) (string, error) {
	if strings.TrimSpace(value) == "" {
		return "", errors.New(localization.Text("storage_path cannot be empty"))
	}
	path, err := filepath.Abs(value)
	if err != nil {
		return "", err
	}
	path = filepath.Clean(path)
	root := filepath.VolumeName(path) + string(filepath.Separator)
	if strings.EqualFold(path, root) {
		return "", errors.New(localization.Text("storage_path cannot be a filesystem root"))
	}
	return path, nil
}

func pathWithin(target, directory string) bool {
	relative, err := filepath.Rel(directory, target)
	return err == nil && relative != ".." && !strings.HasPrefix(relative, ".."+string(filepath.Separator))
}

func currentTime(now func() time.Time) time.Time {
	if now == nil {
		return time.Now().UTC()
	}
	return now().UTC()
}

// resolveOutputPath 生成默认文件名并拒绝覆盖已有文件。
func resolveOutputPath(storagePath, value, prefix string, createdAt time.Time) (string, error) {
	filename := fmt.Sprintf("%s-%s.tar.gz", prefix, createdAt.Format("20060102T150405Z"))
	path := value
	if path == "" {
		path = filepath.Join(storagePath, "backups", filename)
	} else if info, err := os.Stat(path); err == nil && info.IsDir() {
		path = filepath.Join(path, filename)
	} else if err != nil && !errors.Is(err, os.ErrNotExist) {
		return "", err
	}
	path, err := filepath.Abs(path)
	if err != nil {
		return "", err
	}
	if _, err := os.Lstat(path); err == nil {
		return "", fmt.Errorf(localization.Text("backup file already exists: %s"), path)
	} else if !errors.Is(err, os.ErrNotExist) {
		return "", err
	}
	return path, nil
}

// requireBackupSources 确认备份所需的业务分库和运行配置完整存在。
func requireBackupSources(storagePath string) error {
	for _, relative := range []string{".env", "database/main.sqlite", "database/rag.sqlite"} {
		path := filepath.Join(storagePath, filepath.FromSlash(relative))
		info, err := os.Stat(path)
		if err != nil {
			return fmt.Errorf(localization.Text("backup source is missing %s: %w"), relative, err)
		}
		if !info.Mode().IsRegular() {
			return fmt.Errorf(localization.Text("backup source is not a regular file: %s"), path)
		}
	}
	return nil
}

// stageBackup 将数据库快照和本地业务文件写入独立暂存目录。
func stageBackup(storagePath, stagePath string, snapshot SnapshotDatabasesFunc) error {
	mainDestination := filepath.Join(stagePath, "database", "main.sqlite")
	ragDestination := filepath.Join(stagePath, "database", "rag.sqlite")
	if err := os.MkdirAll(filepath.Dir(mainDestination), 0700); err != nil {
		return err
	}
	if err := snapshot(mainDestination, ragDestination); err != nil {
		return fmt.Errorf(localization.Text("failed to create SQLite snapshots: %w"), err)
	}
	for _, databasePath := range []string{mainDestination, ragDestination} {
		if err := validateSQLiteHeader(databasePath); err != nil {
			return err
		}
	}
	if err := copyRegularFile(
		filepath.Join(storagePath, ".env"),
		filepath.Join(stagePath, "runtime", ".env"),
		0600,
	); err != nil {
		return err
	}
	if err := copyTree(
		filepath.Join(storagePath, "app", "private"),
		filepath.Join(stagePath, "files", "private"),
		[]string{"knowledge-temp", filepath.Join("attachments", "pending")},
	); err != nil {
		return err
	}
	if err := copyTree(
		filepath.Join(storagePath, "app", "public"),
		filepath.Join(stagePath, "files", "public"),
		nil,
	); err != nil {
		return err
	}
	return nil
}

// copyTree 将普通文件目录复制到备份暂存区并拒绝符号链接。
func copyTree(source, destination string, excluded []string) error {
	if err := os.MkdirAll(destination, 0700); err != nil {
		return err
	}
	if _, err := os.Stat(source); errors.Is(err, os.ErrNotExist) {
		return nil
	} else if err != nil {
		return err
	}
	return filepath.WalkDir(source, func(path string, entry fs.DirEntry, walkErr error) error {
		if walkErr != nil {
			return walkErr
		}
		relative, err := filepath.Rel(source, path)
		if err != nil {
			return err
		}
		if excludedTreePath(relative, excluded) {
			if entry.IsDir() {
				return filepath.SkipDir
			}
			return nil
		}
		target := filepath.Join(destination, relative)
		info, err := entry.Info()
		if err != nil {
			return err
		}
		switch {
		case entry.Type()&os.ModeSymlink != 0:
			return fmt.Errorf(localization.Text("business file directory contains a symbolic link: %s"), path)
		case entry.IsDir():
			return os.MkdirAll(target, info.Mode().Perm())
		case info.Mode().IsRegular():
			return copyRegularFile(path, target, info.Mode().Perm())
		default:
			return fmt.Errorf(localization.Text("business file directory contains an unsupported file type: %s"), path)
		}
	})
}

func excludedTreePath(relative string, excluded []string) bool {
	for _, item := range excluded {
		if relative == item || strings.HasPrefix(relative, item+string(filepath.Separator)) {
			return true
		}
	}
	return false
}

func copyRegularFile(source, destination string, mode os.FileMode) error {
	input, err := os.Open(source)
	if err != nil {
		return err
	}
	defer input.Close()
	if err := os.MkdirAll(filepath.Dir(destination), 0700); err != nil {
		return err
	}
	output, err := os.OpenFile(destination, os.O_CREATE|os.O_EXCL|os.O_WRONLY, mode)
	if err != nil {
		return err
	}
	if _, err := io.Copy(output, input); err != nil {
		output.Close()
		return err
	}
	return output.Close()
}

// buildManifest 计算暂存文件的大小和 SHA-256 摘要。
func buildManifest(stagePath string, createdAt time.Time) (manifest, error) {
	files := make([]manifestFile, 0)
	err := filepath.WalkDir(stagePath, func(path string, entry fs.DirEntry, walkErr error) error {
		if walkErr != nil {
			return walkErr
		}
		if entry.IsDir() {
			return nil
		}
		info, err := entry.Info()
		if err != nil {
			return err
		}
		if !info.Mode().IsRegular() {
			return fmt.Errorf(localization.Text("staging directory contains an unsupported file type: %s"), path)
		}
		relative, err := filepath.Rel(stagePath, path)
		if err != nil {
			return err
		}
		digest, err := fileSHA256(path)
		if err != nil {
			return err
		}
		files = append(files, manifestFile{
			Path:   filepath.ToSlash(relative),
			Size:   info.Size(),
			SHA256: digest,
		})
		return nil
	})
	if err != nil {
		return manifest{}, err
	}
	sort.Slice(files, func(left, right int) bool {
		return files[left].Path < files[right].Path
	})
	return manifest{
		FormatVersion: formatVersion,
		CreatedAt:     createdAt.Format(time.RFC3339),
		Files:         files,
	}, nil
}

func fileSHA256(path string) (string, error) {
	file, err := os.Open(path)
	if err != nil {
		return "", err
	}
	defer file.Close()
	hash := sha256.New()
	if _, err := io.Copy(hash, file); err != nil {
		return "", err
	}
	return hex.EncodeToString(hash.Sum(nil)), nil
}

// validateSQLiteHeader 确认快照具有 SQLite 3 文件头。
func validateSQLiteHeader(path string) error {
	file, err := os.Open(path)
	if err != nil {
		return err
	}
	defer file.Close()
	header := make([]byte, 16)
	if _, err := io.ReadFull(file, header); err != nil {
		return fmt.Errorf(localization.Text("invalid SQLite snapshot: %s"), path)
	}
	if string(header) != "SQLite format 3\x00" {
		return fmt.Errorf(localization.Text("SQLite snapshot has an invalid header: %s"), path)
	}
	return nil
}
