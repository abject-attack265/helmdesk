package runtime

import (
	"bufio"
	"crypto/rand"
	"encoding/base64"
	"errors"
	"fmt"
	"log/slog"
	"os"
	"path/filepath"
	"strings"

	"helmdesk/internal/localization"
	"helmdesk/internal/runtimeconfig"

	"github.com/dunglas/frankenphp"
)

type config struct {
	runtimeConfig        runtimeconfig.Config
	certificateCachePath string
	projectRoot          string
	storagePath          string
	phpEnv               map[string]string
	env                  map[string]string
}

// newConfig 根据已校验的服务配置准备 Laravel 运行环境。
func newConfig(options runtimeconfig.Config) (*config, error) {
	projectRoot, err := resolveProjectRoot()
	if err != nil {
		return nil, err
	}
	storagePath, err := ResolveStoragePath(options)
	if err != nil {
		return nil, err
	}

	if err := prepareStorage(storagePath); err != nil {
		return nil, err
	}
	certificateCachePath := ""
	if options.TLSMode == runtimeconfig.TLSModeManaged {
		certificateCachePath = filepath.Join(storagePath, "certs")
		if err := prepareCertificateCache(certificateCachePath); err != nil {
			return nil, err
		}
	}
	envPath := filepath.Join(projectRoot, ".env")
	if frankenphp.EmbeddedAppPath != "" {
		externalEnv := filepath.Join(storagePath, ".env")
		if err := ensureRuntimeEnv(externalEnv); err != nil {
			return nil, err
		}
		contents, readErr := os.ReadFile(externalEnv)
		if readErr != nil {
			return nil, readErr
		}
		if err := os.WriteFile(envPath, contents, 0600); err != nil {
			return nil, err
		}
	}

	env, err := readEnv(envPath)
	if err != nil {
		return nil, err
	}
	phpEnv := map[string]string{
		"APP_URL":              options.PublicURL,
		"LARAVEL_STORAGE_PATH": storagePath,
		"MAX_REQUESTS":         "500",
	}
	if options.TLSMode == runtimeconfig.TLSModeExternal {
		phpEnv["HELMDESK_TRUSTED_PROXIES"] = strings.Join(options.TrustedProxies, ",")
	}
	if options.TLSMode != runtimeconfig.TLSModePlain {
		phpEnv["SESSION_SECURE_COOKIE"] = "true"
	}

	return &config{
		runtimeConfig:        options,
		certificateCachePath: certificateCachePath,
		projectRoot:          projectRoot,
		storagePath:          storagePath,
		phpEnv:               phpEnv,
		env:                  env,
	}, nil
}

// resolveProjectRoot 返回源码开发目录或内嵌 Laravel 应用目录。
func resolveProjectRoot() (string, error) {
	path := "."
	if frankenphp.EmbeddedAppPath != "" {
		path = frankenphp.EmbeddedAppPath
	}
	return filepath.Abs(path)
}

// ResolveStoragePath 返回运行时实际使用的绝对数据目录。
func ResolveStoragePath(options runtimeconfig.Config) (string, error) {
	if options.StoragePath != "" {
		return filepath.Abs(options.StoragePath)
	}
	if frankenphp.EmbeddedAppPath == "" {
		root, err := resolveProjectRoot()
		if err != nil {
			return "", err
		}
		return filepath.Join(root, "storage"), nil
	}
	executable, err := os.Executable()
	if err != nil {
		return "", err
	}
	return filepath.Join(filepath.Dir(executable), "storage"), nil
}

// prepareStorage 创建运行目录和 SQLite 数据库文件。
func prepareStorage(root string) error {
	directories := []string{
		"app/public", "framework/cache/data", "framework/cache/opcache",
		"framework/sessions", "framework/views", "framework/workers", "logs", "database", "scout",
	}
	for _, directory := range directories {
		if err := os.MkdirAll(filepath.Join(root, directory), 0755); err != nil {
			return err
		}
	}
	for _, name := range []string{"main.sqlite", "rag.sqlite", "session.sqlite", "cache.sqlite", "jobs.sqlite"} {
		file, err := os.OpenFile(filepath.Join(root, "database", name), os.O_CREATE, 0644)
		if err != nil {
			return err
		}
		if err := file.Close(); err != nil {
			return err
		}
	}
	return nil
}

// prepareCertificateCache 创建仅供运行账户访问的证书目录。
func prepareCertificateCache(path string) error {
	if err := os.MkdirAll(path, 0700); err != nil {
		return fmt.Errorf(localization.Text("failed to create certificate directory: %w"), err)
	}
	if err := os.Chmod(path, 0700); err != nil {
		return fmt.Errorf(localization.Text("failed to set certificate directory permissions: %w"), err)
	}

	return nil
}

// ensureRuntimeEnv 确保打包运行时存在密钥配置。
func ensureRuntimeEnv(path string) error {
	if _, err := os.Stat(path); err == nil {
		return nil
	} else if !errors.Is(err, os.ErrNotExist) {
		return err
	}
	appKey, err := randomSecret()
	if err != nil {
		return err
	}
	mercureSecret, err := randomSecret()
	if err != nil {
		return err
	}
	contents := fmt.Sprintf("APP_ENV=production\nAPP_KEY=base64:%s\nAPP_DEBUG=false\nMERCURE_PUBLISHER_JWT=%s\nMERCURE_SUBSCRIBER_JWT=%s\n", appKey, mercureSecret, mercureSecret)
	if err := os.WriteFile(path, []byte(contents), 0600); err != nil {
		return err
	}
	slog.Info(localization.Text("runtime configuration generated"), "path", path)
	return nil
}

// randomSecret 生成 32 字节随机密钥的 Base64 表示。
func randomSecret() (string, error) {
	buffer := make([]byte, 32)
	if _, err := rand.Read(buffer); err != nil {
		return "", err
	}
	return base64.StdEncoding.EncodeToString(buffer), nil
}

// readEnv 读取 dotenv 文件中的键值配置。
func readEnv(path string) (map[string]string, error) {
	file, err := os.Open(path)
	if err != nil {
		return nil, err
	}
	defer file.Close()

	values := make(map[string]string)
	lineNumber := 0
	scanner := bufio.NewScanner(file)
	for scanner.Scan() {
		lineNumber++
		line := strings.TrimSpace(scanner.Text())
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		key, value, found := strings.Cut(line, "=")
		if !found {
			return nil, fmt.Errorf(localization.Text("%s line %d is missing an equals sign"), path, lineNumber)
		}
		key = strings.TrimSpace(key)
		if key == "" {
			return nil, fmt.Errorf(localization.Text("%s line %d is missing a configuration name"), path, lineNumber)
		}
		values[key] = strings.Trim(strings.TrimSpace(value), "\"'")
	}
	return values, scanner.Err()
}

// value 按进程环境变量和 dotenv 配置的优先级读取值。
func (cfg *config) value(key string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return cfg.env[key]
}
