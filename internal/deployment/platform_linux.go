//go:build linux

package deployment

import (
	"errors"
	"fmt"
	"io"
	"log/slog"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"time"

	"helmdesk/internal/localization"
	"helmdesk/internal/runtimeconfig"
	"helmdesk/internal/storageguard"
)

const (
	serviceName = "helmdesk.service"
	// DefaultStoragePath 是 systemd 安装后的默认数据目录。
	DefaultStoragePath = "/var/lib/helmdesk"
	systemBinaryPath   = "/usr/local/bin/helmdesk"
	previousBinaryPath = "/usr/local/bin/helmdesk.previous"
	systemConfigPath   = "/etc/helmdesk/config.json"
	systemUnitPath     = "/etc/systemd/system/helmdesk.service"
	upgradeLockPath    = "/run/helmdesk-upgrade"
)

// ConfigPath 返回 Linux 系统安装配置路径。
func ConfigPath() string {
	return systemConfigPath
}

// DefaultRuntimeConfig 返回 Linux 前台运行配置。
func DefaultRuntimeConfig() runtimeconfig.Config {
	return runtimeconfig.Default()
}

// UpgradeDownloadDirectory 返回 Linux 系统安装升级文件的暂存目录。
func UpgradeDownloadDirectory() string {
	return filepath.Dir(systemBinaryPath)
}

// ValidateUpgradeInstallation 确认当前用户具有 root 权限且 systemd 安装存在。
func ValidateUpgradeInstallation() error {
	if os.Geteuid() != 0 {
		return errors.New(localization.Text("upgrade must be run as root"))
	}
	if _, err := os.Stat(systemUnitPath); errors.Is(err, os.ErrNotExist) {
		return errors.New(localization.Text("automatic upgrade requires a systemd installation"))
	} else if err != nil {
		return err
	}
	return nil
}

// UpgradeService 停止服务、备份数据、替换二进制并在失败时恢复升级前状态。
func UpgradeService(candidatePath, targetVersion string) (string, error) {
	upgradeLock, err := storageguard.Acquire(upgradeLockPath)
	if err != nil {
		if errors.Is(err, storageguard.ErrInUse) {
			return "", errors.New(localization.Text("another HelmDesk upgrade is already running"))
		}
		return "", err
	}
	defer func() {
		if err := upgradeLock.Close(); err != nil {
			slog.Warn(localization.Text("failed to release upgrade lock"), "path", upgradeLockPath, "error", err)
		}
	}()
	config, err := LoadConfig(systemConfigPath)
	if err != nil {
		return "", err
	}
	slog.Info(
		localization.Text("starting HelmDesk upgrade"),
		"target_version", targetVersion,
		"storage_path", config.StoragePath,
	)
	slog.Info(localization.Text("stopping HelmDesk service for upgrade"))
	if err := ControlService("stop"); err != nil {
		return "", err
	}
	lock, err := storageguard.Acquire(config.StoragePath)
	if err != nil {
		startErr := ControlService("start")
		return "", errors.Join(err, startErr)
	}
	lockHeld := true
	closeLock := func() error {
		if !lockHeld {
			return nil
		}
		lockHeld = false
		return lock.Close()
	}
	resumeOriginal := func(upgradeErr error) (string, error) {
		slog.Warn(localization.Text("upgrade preparation failed; restarting the current version"), "error", upgradeErr)
		closeErr := closeLock()
		startErr := ControlService("start")
		return "", errors.Join(upgradeErr, closeErr, startErr)
	}
	slog.Info(localization.Text("saving current HelmDesk binary"), "path", previousBinaryPath)
	if err := copyExecutable(systemBinaryPath, previousBinaryPath); err != nil {
		return resumeOriginal(err)
	}
	slog.Info(localization.Text("creating pre-upgrade backup"), "target_version", targetVersion)
	backupPath, err := createUpgradeBackup(config, targetVersion)
	if err != nil {
		return resumeOriginal(err)
	}
	slog.Info(localization.Text("installing HelmDesk update"), "target_version", targetVersion)
	if err := copyExecutable(candidatePath, systemBinaryPath); err != nil {
		return resumeOriginal(err)
	}
	if err := closeLock(); err != nil {
		return "", recoverUpgrade(config, backupPath, err)
	}
	slog.Info(localization.Text("starting upgraded HelmDesk service"), "target_version", targetVersion)
	if err := ControlService("start"); err != nil {
		return "", recoverUpgrade(config, backupPath, err)
	}
	slog.Info(localization.Text("waiting for upgraded HelmDesk to become ready"))
	if err := waitForService(config.HTTPAddress, 5*time.Minute); err != nil {
		return "", recoverUpgrade(config, backupPath, err)
	}
	slog.Info(
		localization.Text("HelmDesk upgrade completed"),
		"target_version", targetVersion,
		"backup_path", backupPath,
	)
	return backupPath, nil
}

// recoverUpgrade 恢复升级前的二进制和业务数据。
func recoverUpgrade(config runtimeconfig.Config, backupPath string, upgradeErr error) error {
	slog.Warn(localization.Text("HelmDesk upgrade failed; starting automatic recovery"), "error", upgradeErr)
	recoveryErr := ControlService("stop")
	slog.Info(localization.Text("restoring previous HelmDesk binary"), "path", systemBinaryPath)
	if err := copyExecutable(previousBinaryPath, systemBinaryPath); err != nil {
		recoveryErr = errors.Join(recoveryErr, err)
	}
	slog.Info(localization.Text("restoring pre-upgrade business data"), "backup_path", backupPath)
	if err := restoreUpgradeBackup(config, backupPath); err != nil {
		recoveryErr = errors.Join(recoveryErr, err)
	}
	if recoveryErr == nil {
		slog.Info(localization.Text("starting restored HelmDesk service"))
		if err := ControlService("start"); err != nil {
			recoveryErr = errors.Join(recoveryErr, err)
		} else if err := waitForService(config.HTTPAddress, 5*time.Minute); err != nil {
			recoveryErr = errors.Join(recoveryErr, err)
		}
	}
	if recoveryErr != nil {
		return errors.Join(
			fmt.Errorf(localization.Text("upgrade failed: %w"), upgradeErr),
			fmt.Errorf(localization.Text("automatic recovery failed: %w"), recoveryErr),
		)
	}
	return fmt.Errorf(localization.Text("upgrade failed and the previous version was restored: %w"), upgradeErr)
}

// waitForService 等待 systemd 服务进入 active 并通过 Laravel 健康检查。
func waitForService(address string, timeout time.Duration) error {
	statusURL, err := runtimeStatusURL(address)
	if err != nil {
		return err
	}
	client := http.Client{Timeout: time.Second}
	deadline := time.Now().Add(timeout)
	for time.Now().Before(deadline) {
		if serviceActive() {
			response, requestErr := client.Get(statusURL)
			if requestErr == nil {
				if closeErr := response.Body.Close(); closeErr != nil {
					slog.Warn(localization.Text("failed to close health check response"), "url", statusURL, "error", closeErr)
				}
				if response.StatusCode == http.StatusOK {
					return nil
				}
			}
		}
		time.Sleep(time.Second)
	}
	return fmt.Errorf(localization.Text("timed out waiting for HelmDesk to become ready on %s"), statusURL)
}

// serviceActive 确认 systemd 服务仍处于 active 状态。
func serviceActive() bool {
	return exec.Command("systemctl", "is-active", "--quiet", serviceName).Run() == nil
}

// InstallService 安装二进制、运行配置和 systemd 服务。
func InstallService(serviceConfig runtimeconfig.Config) error {
	if os.Geteuid() != 0 {
		return errors.New(localization.Text("install must be run as root"))
	}
	if _, err := os.Stat("/run/systemd/system"); errors.Is(err, os.ErrNotExist) {
		return errors.New(localization.Text("systemd is not running on this Linux system"))
	} else if err != nil {
		return fmt.Errorf(localization.Text("failed to check systemd status: %w"), err)
	}
	config, err := normalizeInstallConfig(serviceConfig)
	if err != nil {
		return err
	}
	slog.Info(
		localization.Text("installing HelmDesk service"),
		"public_url", config.PublicURL,
		"tls_mode", config.TLSMode,
		"http_address", config.HTTPAddress,
		"https_address", config.HTTPSAddress,
		"storage_path", config.StoragePath,
	)
	if err := runCommand("groupadd", "--system", "--force", "helmdesk"); err != nil {
		return err
	}
	exists, err := systemUserExists("helmdesk")
	if err != nil {
		return err
	}
	if !exists {
		if err := runCommand(
			"useradd", "--system", "--gid", "helmdesk", "--home-dir", config.StoragePath,
			"--shell", "/usr/sbin/nologin", "helmdesk",
		); err != nil {
			return err
		}
	}
	if err := os.MkdirAll(config.StoragePath, 0750); err != nil {
		return err
	}
	if err := runCommand("chown", "-R", "helmdesk:helmdesk", config.StoragePath); err != nil {
		return err
	}
	source, err := ExecutablePath()
	if err != nil {
		return err
	}
	if err := copyExecutable(source, systemBinaryPath); err != nil {
		return err
	}
	if err := writeConfig(systemConfigPath, config); err != nil {
		return err
	}
	unit, err := systemdUnit(systemBinaryPath, systemConfigPath, config)
	if err != nil {
		return err
	}
	if err := writeFileAtomic(systemUnitPath, []byte(unit), 0644); err != nil {
		return err
	}
	if err := runCommand("systemctl", "daemon-reload"); err != nil {
		return err
	}
	if err := runCommand("systemctl", "enable", serviceName); err != nil {
		return err
	}
	if err := runCommand("systemctl", "restart", serviceName); err != nil {
		return err
	}
	slog.Info(localization.Text("HelmDesk has been installed and started"), "public_url", config.PublicURL, "storage_path", config.StoragePath)
	return nil
}

// ControlService 通过 systemctl 执行服务控制命令。
func ControlService(action string) error {
	switch action {
	case "start", "stop", "restart", "status":
	default:
		return fmt.Errorf(localization.Text("unsupported service operation: %s"), action)
	}
	arguments := []string{action, serviceName}
	if action == "status" {
		arguments = append(arguments, "--no-pager")
	}
	command := exec.Command("systemctl", arguments...)
	command.Stdin = os.Stdin
	command.Stdout = os.Stdout
	command.Stderr = os.Stderr
	if err := command.Run(); err != nil {
		return err
	}
	if action != "status" {
		slog.Info(localization.Text("HelmDesk service operation completed"), "action", action)
	}
	return nil
}

// Status 通过 systemctl 输出 HelmDesk 服务状态。
func Status() error {
	return ControlService("status")
}

// ShowServiceLogs 通过 journalctl 输出 HelmDesk 服务运行日志。
func ShowServiceLogs(lines int, follow bool, since string) error {
	arguments := []string{
		"--unit", serviceName,
		"--lines", fmt.Sprintf("%d", lines),
		"--no-pager",
	}
	if since != "" {
		arguments = append(arguments, "--since", since)
	}
	if follow {
		arguments = append(arguments, "--follow")
	}
	return runCommand("journalctl", arguments...)
}

// UninstallService 停止并移除服务程序，保留业务数据。
func UninstallService() error {
	if os.Geteuid() != 0 {
		return errors.New(localization.Text("uninstall must be run as root"))
	}
	slog.Info(localization.Text("uninstalling HelmDesk service"))
	if _, err := os.Stat(systemUnitPath); err == nil {
		if err := runCommand("systemctl", "disable", "--now", serviceName); err != nil {
			return err
		}
	} else if errors.Is(err, os.ErrNotExist) {
		slog.Warn(localization.Text("HelmDesk systemd unit was not found; cleaning up the program and runtime configuration"))
	} else {
		return err
	}
	if err := os.Remove(systemUnitPath); err != nil && !errors.Is(err, os.ErrNotExist) {
		return err
	}
	if err := runCommand("systemctl", "daemon-reload"); err != nil {
		return err
	}
	if err := os.Remove(systemBinaryPath); err != nil && !errors.Is(err, os.ErrNotExist) {
		return err
	}
	if err := os.Remove(previousBinaryPath); err != nil && !errors.Is(err, os.ErrNotExist) {
		return err
	}
	if err := os.Remove(systemConfigPath); err != nil && !errors.Is(err, os.ErrNotExist) {
		return err
	}
	slog.Info(localization.Text("HelmDesk has been uninstalled; business data was retained"))
	return nil
}

// ExitCode 返回系统命令的进程退出码。
func ExitCode(err error) int {
	var exitError *exec.ExitError
	if errors.As(err, &exitError) {
		return exitError.ExitCode()
	}
	return 1
}

// ExecutablePath 返回当前二进制的规范化路径。
func ExecutablePath() (string, error) {
	path, err := os.Executable()
	if err != nil {
		return "", err
	}
	return filepath.EvalSymlinks(path)
}

// systemUserExists 检查指定 Linux 系统用户是否存在。
func systemUserExists(name string) (bool, error) {
	err := exec.Command("id", "--user", name).Run()
	if err == nil {
		return true, nil
	}
	var exitError *exec.ExitError
	if errors.As(err, &exitError) && exitError.ExitCode() == 1 {
		return false, nil
	}
	return false, fmt.Errorf(localization.Text("failed to check system user: %w"), err)
}

// copyExecutable 将当前二进制安装到系统路径。
func copyExecutable(source, destination string) error {
	resolvedSource, err := filepath.EvalSymlinks(source)
	if err != nil {
		return err
	}
	if resolvedSource == destination {
		return os.Chmod(destination, 0755)
	}
	input, err := os.Open(resolvedSource)
	if err != nil {
		return err
	}
	defer input.Close()
	if err := os.MkdirAll(filepath.Dir(destination), 0755); err != nil {
		return err
	}
	output, err := os.CreateTemp(filepath.Dir(destination), ".helmdesk.*")
	if err != nil {
		return err
	}
	tempPath := output.Name()
	defer os.Remove(tempPath)
	if err := output.Chmod(0755); err != nil {
		output.Close()
		return err
	}
	if _, err := io.Copy(output, input); err != nil {
		output.Close()
		return err
	}
	if err := output.Sync(); err != nil {
		output.Close()
		return err
	}
	if err := output.Close(); err != nil {
		return err
	}
	return os.Rename(tempPath, destination)
}

// runCommand 运行安装流程所需的系统命令并透传输出。
func runCommand(name string, arguments ...string) error {
	command := exec.Command(name, arguments...)
	command.Stdout = os.Stdout
	command.Stderr = os.Stderr
	if err := command.Run(); err != nil {
		return fmt.Errorf(localization.Text("%s failed: %w"), name, err)
	}
	return nil
}
