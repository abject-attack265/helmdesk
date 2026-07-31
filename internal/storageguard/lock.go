package storageguard

import (
	"errors"
	"fmt"
	"os"
	"path/filepath"

	"helmdesk/internal/localization"
)

// ErrInUse 表示另一个进程正在使用同一运行目录。
var ErrInUse = localization.New("runtime directory is in use")

// Lock 持有运行目录的跨进程独占锁。
type Lock struct {
	file *os.File
}

// Acquire 获取运行目录的非阻塞独占锁。
func Acquire(storagePath string) (*Lock, error) {
	if err := os.MkdirAll(storagePath, 0755); err != nil {
		return nil, err
	}
	path := filepath.Join(storagePath, ".helmdesk.lock")
	file, err := os.OpenFile(path, os.O_CREATE|os.O_RDWR, 0600)
	if err != nil {
		return nil, err
	}
	if err := lockFile(file); err != nil {
		file.Close()
		if errors.Is(err, ErrInUse) {
			return nil, fmt.Errorf("%w: %s", ErrInUse, storagePath)
		}
		return nil, err
	}

	return &Lock{file: file}, nil
}

// Close 释放运行目录锁。
func (lock *Lock) Close() error {
	unlockErr := unlockFile(lock.file)
	closeErr := lock.file.Close()

	return errors.Join(unlockErr, closeErr)
}
