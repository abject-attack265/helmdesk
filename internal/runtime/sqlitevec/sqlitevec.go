package sqlitevec

import (
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"os"
	"path/filepath"
	"runtime"

	"helmdesk/internal/localization"
)

type artifact struct {
	path string
	hash string
}

var artifacts = map[string]artifact{
	"linux/amd64":  {filepath.Join("bootstrap", "sqlite_vec", "linux-amd64", "vec0.so"), "5923730861b86c707cca5602b5f91092f9e52a46706dbc6e269fd4bb9c4498e8"},
	"linux/arm64":  {filepath.Join("bootstrap", "sqlite_vec", "linux-arm64", "vec0.so"), "0b84cbd06418ca3040827deddd650539be05be0f657952426b926c8606217437"},
	"darwin/arm64": {filepath.Join("bootstrap", "sqlite_vec", "macos-arm64", "vec0.dylib"), "193e480c50b59a55977d166f4aaf0e1bc8832d6963516e5950f39e4d2ce0b793"},
}

// Register 校验当前平台的 sqlite-vec 文件并注册扩展。
func Register(projectRoot string) error {
	platform := runtime.GOOS + "/" + runtime.GOARCH
	if platform == "windows/amd64" {
		return register("")
	}
	item, supported := artifacts[platform]
	if !supported {
		return fmt.Errorf(localization.Text("sqlite-vec is not supported on this platform: %s"), platform)
	}
	path := filepath.Join(projectRoot, item.path)
	contents, err := os.ReadFile(path)
	if err != nil {
		return err
	}
	hash := sha256.Sum256(contents)
	if hex.EncodeToString(hash[:]) != item.hash {
		return fmt.Errorf(localization.Text("sqlite-vec validation failed: %s"), path)
	}
	return register(path)
}
