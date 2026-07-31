//go:build linux || darwin

package backup

import (
	"errors"
	"io/fs"
	"os"
	"path/filepath"
	"syscall"

	"helmdesk/internal/localization"
)

// matchOwnership 将目标的 Unix 用户和组设置为参考路径的所有者。
func matchOwnership(target, reference string) error {
	info, err := os.Stat(reference)
	if err != nil {
		return err
	}
	stat, ok := info.Sys().(*syscall.Stat_t)
	if !ok {
		return errors.New(localization.Text("failed to read runtime directory owner"))
	}
	return os.Chown(target, int(stat.Uid), int(stat.Gid))
}

// matchTreeOwnership 递归继承运行目录的 Unix 用户和组。
func matchTreeOwnership(root, reference string) error {
	if _, err := os.Lstat(root); errors.Is(err, os.ErrNotExist) {
		return nil
	} else if err != nil {
		return err
	}
	return filepath.WalkDir(root, func(path string, _ fs.DirEntry, walkErr error) error {
		if walkErr != nil {
			return walkErr
		}
		return matchOwnership(path, reference)
	})
}
