//go:build windows

package backup

// matchOwnership 保留 Windows 当前用户创建的文件 ACL。
func matchOwnership(string, string) error {
	return nil
}

// matchTreeOwnership 保留 Windows 当前用户创建的目录树 ACL。
func matchTreeOwnership(string, string) error {
	return nil
}
