//go:build !windows

package storageguard

import (
	"errors"
	"os"

	"golang.org/x/sys/unix"
)

// lockFile 使用 flock 获取非阻塞独占锁。
func lockFile(file *os.File) error {
	err := unix.Flock(int(file.Fd()), unix.LOCK_EX|unix.LOCK_NB)
	if errors.Is(err, unix.EWOULDBLOCK) || errors.Is(err, unix.EAGAIN) {
		return ErrInUse
	}
	return err
}

// unlockFile 释放 flock 独占锁。
func unlockFile(file *os.File) error {
	return unix.Flock(int(file.Fd()), unix.LOCK_UN)
}
