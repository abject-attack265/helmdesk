//go:build windows

package main

import (
	"errors"
	"flag"
	"fmt"
	"os"

	"helmdesk/internal/deployment"
	"helmdesk/internal/localization"
)

// runPlatformCommand 处理 Windows status 健康检查命令。
func runPlatformCommand(arguments []string) (bool, int) {
	switch arguments[0] {
	case "upgrade":
		return true, runUpgrade(arguments[1:])
	case "status":
	default:
		return false, 0
	}
	if len(arguments) != 1 {
		fmt.Fprintln(os.Stderr, localization.Text("status does not accept arguments"))
		return true, 2
	}
	if err := deployment.Status(); err != nil {
		fmt.Fprintln(os.Stderr, err)
		return true, 1
	}
	return true, 0
}

// runUpgrade 下载最新正式安装器并交给 Windows 图形安装流程。
func runUpgrade(arguments []string) int {
	checkOnly, err := parseUpgradeArguments(arguments)
	if err != nil {
		if errors.Is(err, flag.ErrHelp) {
			return 0
		}
		fmt.Fprintln(os.Stderr, err)
		return 2
	}
	ctx, cancel := upgradeContext()
	defer cancel()
	client, update, available, err := checkUpgrade(ctx)
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		return 1
	}
	if !available || checkOnly {
		return 0
	}
	if err := deployment.ValidateUpgradeInstallation(); err != nil {
		fmt.Fprintln(os.Stderr, err)
		return 1
	}
	config, err := installedConfig("")
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		return 1
	}
	config, err = normalizeRuntimeConfig(applyEnvironment(config))
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		return 1
	}
	confirmed, err := confirmUpgrade()
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		return 1
	}
	if !confirmed {
		fmt.Fprintln(os.Stdout, localization.Text("Upgrade cancelled."))
		return 0
	}
	path, err := downloadUpgrade(ctx, client, update, deployment.UpgradeDownloadDirectory())
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		return 1
	}
	backupPath, err := deployment.PrepareInstallerUpgrade(config, update.Version)
	if err != nil {
		removeDownloadedUpdate(path)
		fmt.Fprintln(os.Stderr, err)
		return 1
	}
	fmt.Fprintln(os.Stdout, localization.Text("Opening the HelmDesk installer..."))
	fmt.Printf(localization.Text("Pre-upgrade backup: %s\n"), backupPath)
	if err := deployment.LaunchUpgradeInstaller(path); err != nil {
		removeDownloadedUpdate(path)
		fmt.Fprintln(os.Stderr, err)
		return 1
	}
	return 0
}

func platformUsage() string {
	return localization.Text(`  helmdesk upgrade [--check]
  helmdesk status
`)
}
