package backup

import "time"

const formatVersion = 1

// SnapshotDatabasesFunc 为主库和知识检索库生成一致性快照。
type SnapshotDatabasesFunc func(mainDestination, ragDestination string) error

// CreateOptions 定义备份来源、输出位置和数据库快照实现。
type CreateOptions struct {
	StoragePath       string
	OutputPath        string
	Now               func() time.Time
	SnapshotDatabases SnapshotDatabasesFunc
}

// CreateResult 描述已经生成的备份文件。
type CreateResult struct {
	Path string
	Size int64
}

// RestoreOptions 定义离线恢复的目标、数据库快照和实例初始化实现。
type RestoreOptions struct {
	StoragePath       string
	ArchivePath       string
	Now               func() time.Time
	SnapshotDatabases SnapshotDatabasesFunc
	PrepareInstance   func() error
}

// RestoreResult 描述恢复来源和恢复前安全快照。
type RestoreResult struct {
	ArchivePath      string
	SafetyBackupPath string
}

type manifest struct {
	FormatVersion int            `json:"format_version"`
	CreatedAt     string         `json:"created_at"`
	Files         []manifestFile `json:"files"`
}

type manifestFile struct {
	Path   string `json:"path"`
	Size   int64  `json:"size"`
	SHA256 string `json:"sha256"`
}
