package deployment

import (
	"encoding/json"
	"errors"
	"fmt"
	"net"
	"net/url"
	"os"
	"path/filepath"

	"helmdesk/internal/localization"
	"helmdesk/internal/runtimeconfig"
)

// LoadConfig 读取安装程序生成的运行配置。
func LoadConfig(path string) (runtimeconfig.Config, error) {
	contents, err := os.ReadFile(path)
	if err != nil {
		return runtimeconfig.Config{}, err
	}
	var config runtimeconfig.Config
	if err := json.Unmarshal(contents, &config); err != nil {
		return runtimeconfig.Config{}, fmt.Errorf(localization.Text("failed to parse runtime configuration: %w"), err)
	}
	if config.PublicURL == "" {
		return runtimeconfig.Config{}, errors.New(localization.Text("runtime configuration is missing public_url"))
	}
	if config.TLSMode == "" {
		return runtimeconfig.Config{}, errors.New(localization.Text("runtime configuration is missing tls_mode"))
	}
	if config.HTTPAddress == "" {
		return runtimeconfig.Config{}, errors.New(localization.Text("runtime configuration is missing http_address"))
	}
	if config.TLSMode == runtimeconfig.TLSModeManaged && config.HTTPSAddress == "" {
		return runtimeconfig.Config{}, errors.New(localization.Text("managed TLS runtime configuration is missing https_address"))
	}
	if config.StoragePath == "" {
		return runtimeconfig.Config{}, errors.New(localization.Text("runtime configuration is missing storage_path"))
	}
	config, err = runtimeconfig.Normalize(config)
	if err != nil {
		return runtimeconfig.Config{}, fmt.Errorf(localization.Text("invalid runtime configuration: %w"), err)
	}

	return config, nil
}

// runtimeStatusURL 生成可由本机访问的 Laravel 健康检查地址。
func runtimeStatusURL(address string) (string, error) {
	host, port, err := net.SplitHostPort(address)
	if err != nil {
		return "", fmt.Errorf(localization.Text("invalid listen address: %w"), err)
	}
	if host == "" || host == "0.0.0.0" || host == "::" {
		host = "127.0.0.1"
	}
	return (&url.URL{
		Scheme: "http",
		Host:   net.JoinHostPort(host, port),
		Path:   "/up",
	}).String(), nil
}

// writeConfig 原子写入服务运行配置。
func writeConfig(path string, config runtimeconfig.Config) error {
	contents, err := json.MarshalIndent(config, "", "  ")
	if err != nil {
		return err
	}
	contents = append(contents, '\n')
	return writeFileAtomic(path, contents, 0644)
}

// writeFileAtomic 通过同目录临时文件替换目标文件。
func writeFileAtomic(path string, contents []byte, mode os.FileMode) error {
	if err := os.MkdirAll(filepath.Dir(path), 0755); err != nil {
		return err
	}
	file, err := os.CreateTemp(filepath.Dir(path), "."+filepath.Base(path)+".*")
	if err != nil {
		return err
	}
	tempPath := file.Name()
	defer os.Remove(tempPath)
	if err := file.Chmod(mode); err != nil {
		file.Close()
		return err
	}
	if _, err := file.Write(contents); err != nil {
		file.Close()
		return err
	}
	if err := file.Sync(); err != nil {
		file.Close()
		return err
	}
	if err := file.Close(); err != nil {
		return err
	}
	return os.Rename(tempPath, path)
}
