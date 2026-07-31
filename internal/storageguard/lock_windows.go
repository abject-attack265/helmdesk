//go:build windows

package storageguard

import (
	"errors"
	"os"

	"golang.org/x/sys/windows"
)

// lockFile 使用 LockFileEx 获取非阻塞独占锁。
func lockFile(file *os.File) error {
	var overlapped windows.Overlapped
	err := windows.LockFileEx(
		windows.Handle(file.Fd()),
		windows.LOCKFILE_EXCLUSIVE_LOCK|windows.LOCKFILE_FAIL_IMMEDIATELY,
		0,
		1,
		0,
		&overlapped,
	)
	if errors.Is(err, windows.ERROR_LOCK_VIOLATION) {
		return ErrInUse
	}
	return err
}

// unlockFile 释放 LockFileEx 独占锁。
func unlockFile(file *os.File) error {
	var overlapped windows.Overlapped
	return windows.UnlockFileEx(windows.Handle(file.Fd()), 0, 1, 0, &overlapped)
}
