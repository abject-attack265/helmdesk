//go:build windows

package deployment

import (
	"errors"
	"fmt"
	"log/slog"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"time"

	"helmdesk/internal/localization"
	"helmdesk/internal/runtimeconfig"
	"helmdesk/internal/storageguard"
)

// DefaultStoragePath 是 Windows 前台运行默认保存配置和业务数据的目录。
var DefaultStoragePath = filepath.Join(programDataPath(), "HelmDesk")

// ConfigPath 返回空路径，Windows 前台运行使用默认配置。
func ConfigPath() string {
	return ""
}

// DefaultRuntimeConfig 返回 Windows 安装包约定的前台运行参数。
func DefaultRuntimeConfig() runtimeconfig.Config {
	config := runtimeconfig.Default()
	config.StoragePath = DefaultStoragePath

	return config
}

// UpgradeDownloadDirectory 返回 Windows 安装器的临时下载目录。
func UpgradeDownloadDirectory() string {
	return os.TempDir()
}

// ValidateUpgradeInstallation 确认当前程序来自标准 Windows 安装目录。
func ValidateUpgradeInstallation() error {
	executable, err := os.Executable()
	if err != nil {
		return err
	}
	programFiles := os.Getenv("ProgramFiles")
	if programFiles == "" {
		return errors.New(localization.Text("the ProgramFiles environment variable cannot be empty"))
	}
	installDirectory := filepath.Join(programFiles, "HelmDesk")
	if !strings.EqualFold(filepath.Clean(filepath.Dir(executable)), filepath.Clean(installDirectory)) {
		return errors.New(localization.Text("automatic upgrade only supports HelmDesk installed by HelmDesk-Setup.exe"))
	}
	return nil
}

// PrepareInstallerUpgrade 确认前台服务已停止并创建升级前备份。
func PrepareInstallerUpgrade(config runtimeconfig.Config, targetVersion string) (string, error) {
	lock, err := storageguard.Acquire(config.StoragePath)
	if err != nil {
		if errors.Is(err, storageguard.ErrInUse) {
			return "", errors.New(localization.Text("stop HelmDesk before running upgrade"))
		}
		return "", err
	}
	slog.Info(
		localization.Text("creating pre-upgrade backup"),
		"target_version", targetVersion,
		"storage_path", config.StoragePath,
	)
	backupPath, backupErr := createUpgradeBackup(config, targetVersion)
	closeErr := lock.Close()
	if backupErr == nil && closeErr == nil {
		slog.Info(localization.Text("pre-upgrade backup created"), "path", backupPath)
	}
	return backupPath, errors.Join(backupErr, closeErr)
}

// LaunchUpgradeInstaller 启动图形安装器并让当前 CLI 进程退出。
func LaunchUpgradeInstaller(path string) error {
	slog.Info(localization.Text("launching HelmDesk upgrade installer"), "path", path)
	command := exec.Command(path)
	if err := command.Start(); err != nil {
		return err
	}
	return command.Process.Release()
}

// Status 检查默认监听地址上的 HelmDesk 健康状态。
func Status() error {
	config := DefaultRuntimeConfig()
	statusURL, err := runtimeStatusURL(config.HTTPAddress)
	if err != nil {
		return err
	}
	client := http.Client{Timeout: 3 * time.Second}
	response, err := client.Get(statusURL)
	if err != nil {
		return fmt.Errorf(localization.Text("HelmDesk is not running (%s): %w"), statusURL, err)
	}
	defer response.Body.Close()
	if response.StatusCode != http.StatusOK {
		return fmt.Errorf(localization.Text("HelmDesk health check failed: %s returned %s"), statusURL, response.Status)
	}
	fmt.Fprintf(os.Stdout, localization.Text("HelmDesk is running: %s\n"), config.PublicURL)
	return nil
}

// programDataPath 返回 Windows 公共应用数据目录并要求系统提供标准路径。
func programDataPath() string {
	path := os.Getenv("ProgramData")
	if path == "" {
		panic(localization.Text("the ProgramData environment variable cannot be empty"))
	}
	return path
}
