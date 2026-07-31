//go:build !linux && !darwin && !windows

package backup

// matchOwnership 保留当前平台创建文件时的默认所有权。
func matchOwnership(string, string) error {
	return nil
}

// matchTreeOwnership 保留当前平台创建目录树时的默认所有权。
func matchTreeOwnership(string, string) error {
	return nil
}
