//go:build linux

package main

import (
	"errors"
	"flag"
	"fmt"
	"os"

	"helmdesk/internal/deployment"
	"helmdesk/internal/localization"
	"helmdesk/internal/runtimeconfig"
)

// runPlatformCommand 执行 Linux systemd 管理命令。
func runPlatformCommand(arguments []string) (bool, int) {
	switch arguments[0] {
	case "install":
		return true, runInstall(arguments[1:])
	case "upgrade":
		return true, runUpgrade(arguments[1:])
	case "start", "stop", "restart":
		if err := deployment.ControlService(arguments[0]); err != nil {
			fmt.Fprintln(os.Stderr, err)
			return true, deployment.ExitCode(err)
		}
		return true, 0
	case "status":
		if err := deployment.Status(); err != nil {
			fmt.Fprintln(os.Stderr, err)
			return true, deployment.ExitCode(err)
		}
		return true, 0
	case "logs":
		return true, runLogs(arguments[1:])
	case "uninstall":
		if err := deployment.UninstallService(); err != nil {
			fmt.Fprintln(os.Stderr, err)
			return true, deployment.ExitCode(err)
		}
		return true, 0
	default:
		return false, 0
	}
}

// runUpgrade 下载最新正式版本并升级 systemd 安装。
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
	defer removeDownloadedUpdate(path)
	if err := validateCandidateVersion(path, update); err != nil {
		fmt.Fprintln(os.Stderr, err)
		return 1
	}
	backupPath, err := deployment.UpgradeService(path, update.Version)
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		return 1
	}
	fmt.Printf(localization.Text("HelmDesk was upgraded to %s.\n"), update.Version)
	fmt.Printf(localization.Text("Pre-upgrade backup: %s\n"), backupPath)
	return 0
}

// runInstall 解析参数并安装 systemd 服务。
func runInstall(arguments []string) int {
	flags := flag.NewFlagSet("install", flag.ContinueOnError)
	flags.SetOutput(os.Stderr)
	runtimeFlags := registerRuntimeFlags(flags)
	if err := flags.Parse(arguments); err != nil {
		if errors.Is(err, flag.ErrHelp) {
			return 0
		}
		return 2
	}
	if flags.NArg() != 0 {
		fmt.Fprintf(os.Stderr, localization.Text("install does not accept positional arguments: %s\n"), flags.Arg(0))
		return 2
	}
	config := applyEnvironment(runtimeconfig.Default())
	config = runtimeFlags.apply(config)
	if config.StoragePath == "" {
		config.StoragePath = deployment.DefaultStoragePath
	}
	if err := deployment.InstallService(config); err != nil {
		fmt.Fprintln(os.Stderr, err)
		return deployment.ExitCode(err)
	}
	return 0
}

// runLogs 解析参数并输出 systemd 服务运行日志。
func runLogs(arguments []string) int {
	flags := flag.NewFlagSet("logs", flag.ContinueOnError)
	flags.SetOutput(os.Stderr)
	var follow bool
	var lines int
	flags.BoolVar(&follow, "follow", false, localization.Text("follow new log entries"))
	flags.BoolVar(&follow, "f", false, localization.Text("follow new log entries"))
	flags.IntVar(&lines, "lines", 200, localization.Text("number of recent log lines to show"))
	flags.IntVar(&lines, "n", 200, localization.Text("number of recent log lines to show"))
	since := flags.String("since", "", localization.Text("show logs since the specified time"))
	if err := flags.Parse(arguments); err != nil {
		if errors.Is(err, flag.ErrHelp) {
			return 0
		}
		return 2
	}
	if flags.NArg() != 0 {
		fmt.Fprintf(os.Stderr, localization.Text("logs does not accept positional arguments: %s\n"), flags.Arg(0))
		return 2
	}
	if lines < 1 {
		fmt.Fprintln(os.Stderr, localization.Text("logs --lines must be greater than 0"))
		return 2
	}
	if err := deployment.ShowServiceLogs(lines, follow, *since); err != nil {
		fmt.Fprintln(os.Stderr, err)
		return deployment.ExitCode(err)
	}
	return 0
}

// platformUsage 返回 Linux systemd 命令帮助。
func platformUsage() string {
	return localization.Text(`  helmdesk install [runtime options]
  helmdesk upgrade [--check]
  helmdesk start
  helmdesk stop
  helmdesk restart
  helmdesk status
  helmdesk logs [--follow] [--lines count] [--since time]
  helmdesk uninstall
`)
}
