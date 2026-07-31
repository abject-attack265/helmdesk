//go:build windows

package sqlitevec

// register 在 Windows 上由 PHP SQLite 连接负责加载扩展。
func register(string) error {
	return nil
}
