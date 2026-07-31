package main

import (
	"bufio"
	"context"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	"log/slog"
	"os"
	"os/exec"
	"runtime"
	"strings"
	"time"

	"helmdesk/internal/buildinfo"
	"helmdesk/internal/localization"
	"helmdesk/internal/updater"
)

// parseUpgradeArguments 解析自动升级的只读检查参数。
func parseUpgradeArguments(arguments []string) (bool, error) {
	flags := flag.NewFlagSet("upgrade", flag.ContinueOnError)
	flags.SetOutput(os.Stderr)
	checkOnly := flags.Bool("check", false, localization.Text("check for updates without installing"))
	if err := flags.Parse(arguments); err != nil {
		return false, err
	}
	if flags.NArg() != 0 {
		return false, fmt.Errorf(localization.Text("upgrade does not accept positional arguments: %s"), flags.Arg(0))
	}
	return *checkOnly, nil
}

// checkUpgrade 查询并展示当前平台的最新正式版本。
func checkUpgrade(ctx context.Context) (updater.Client, updater.Update, bool, error) {
	client := updater.Client{}
	current := buildinfo.Current()
	update, available, err := client.Check(ctx, current.Version, runtime.GOOS, runtime.GOARCH)
	if err != nil {
		return client, updater.Update{}, false, err
	}
	fmt.Printf(localization.Text("Current version: %s\n"), current.Version)
	if !available {
		fmt.Fprintln(os.Stdout, localization.Text("HelmDesk is already up to date."))
		return client, update, false, nil
	}
	fmt.Printf(localization.Text("Latest version:  %s\n"), update.Version)
	fmt.Printf(localization.Text("Platform:        %s/%s\n"), runtime.GOOS, runtime.GOARCH)
	fmt.Printf(localization.Text("Download size:   %d bytes\n"), update.Asset.Size)
	return client, update, true, nil
}

// confirmUpgrade 要求交互确认目标版本和短暂停机。
func confirmUpgrade() (bool, error) {
	fmt.Fprint(os.Stdout, localization.Text("HelmDesk will create a backup before installing the update. Continue? [y/N] "))
	line, err := bufio.NewReader(os.Stdin).ReadString('\n')
	if err != nil && !errors.Is(err, io.EOF) {
		return false, err
	}
	answer := strings.ToLower(strings.TrimSpace(line))
	return answer == "y" || answer == "yes", nil
}

// downloadUpgrade 下载并校验目标平台的发布产物。
func downloadUpgrade(ctx context.Context, client updater.Client, update updater.Update, directory string) (string, error) {
	fmt.Fprintln(os.Stdout, localization.Text("Downloading and verifying the update..."))
	path, err := client.Download(ctx, update, directory)
	if err != nil {
		return "", err
	}
	fmt.Fprintln(os.Stdout, localization.Text("Update download and SHA-256 verification completed."))
	return path, nil
}

// removeDownloadedUpdate 删除无需继续安装的临时升级文件。
func removeDownloadedUpdate(path string) {
	if err := os.Remove(path); err != nil && !errors.Is(err, os.ErrNotExist) {
		slog.Warn(localization.Text("failed to remove downloaded update"), "path", path, "error", err)
	}
}

// validateCandidateVersion 确认 Linux 候选二进制携带目标版本和平台信息。
func validateCandidateVersion(path string, update updater.Update) error {
	command := exec.Command(path, "version", "--json")
	output, err := command.Output()
	if err != nil {
		return fmt.Errorf(localization.Text("failed to read candidate version: %w"), err)
	}
	var info buildinfo.Info
	if err := json.Unmarshal(output, &info); err != nil {
		return fmt.Errorf(localization.Text("failed to decode candidate version: %w"), err)
	}
	if info.Version != update.Version {
		return fmt.Errorf(
			localization.Text("candidate version is %s, expected %s"),
			info.Version,
			update.Version,
		)
	}
	if info.OS != runtime.GOOS || info.Arch != runtime.GOARCH {
		return fmt.Errorf(
			localization.Text("candidate platform is %s/%s, expected %s/%s"),
			info.OS,
			info.Arch,
			runtime.GOOS,
			runtime.GOARCH,
		)
	}
	return nil
}

// upgradeContext 返回覆盖 Release 查询、下载和安装交接的超时上下文。
func upgradeContext() (context.Context, context.CancelFunc) {
	return context.WithTimeout(context.Background(), 45*time.Minute)
}
