//go:build linux

package deployment

import (
	"errors"
	"fmt"
	"path/filepath"
	"strconv"
	"strings"

	"helmdesk/internal/localization"
	"helmdesk/internal/runtimeconfig"
)

// normalizeInstallConfig 校验并规范化 systemd 安装参数。
func normalizeInstallConfig(config runtimeconfig.Config) (runtimeconfig.Config, error) {
	if config.StoragePath == "" {
		return runtimeconfig.Config{}, errors.New(localization.Text("storage-path cannot be empty"))
	}
	if !filepath.IsAbs(config.StoragePath) {
		return runtimeconfig.Config{}, errors.New(localization.Text("storage-path must be an absolute path"))
	}
	storagePath := filepath.Clean(config.StoragePath)
	volumeRoot := filepath.VolumeName(storagePath) + string(filepath.Separator)
	if strings.EqualFold(storagePath, volumeRoot) {
		return runtimeconfig.Config{}, errors.New(localization.Text("storage-path cannot be a filesystem root"))
	}
	config.StoragePath = storagePath

	return runtimeconfig.Normalize(config)
}

// systemdUnit 生成由非特权用户运行的 HelmDesk 服务单元。
func systemdUnit(binaryPath, configPath string, config runtimeconfig.Config) (string, error) {
	for name, value := range map[string]string{
		"binary path":  binaryPath,
		"config path":  configPath,
		"storage path": config.StoragePath,
	} {
		if strings.ContainsAny(value, "\r\n\x00") {
			return "", fmt.Errorf(localization.Text("%s contains invalid characters"), name)
		}
	}
	capabilities := ""
	if config.TLSMode == runtimeconfig.TLSModeManaged {
		capabilities = `AmbientCapabilities=CAP_NET_BIND_SERVICE
CapabilityBoundingSet=CAP_NET_BIND_SERVICE
`
	}

	return `[Unit]
Description=HelmDesk
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
User=helmdesk
Group=helmdesk
ExecStart=` + strconv.Quote(binaryPath) + ` serve --config ` + strconv.Quote(configPath) + `
WorkingDirectory=` + systemdPath(config.StoragePath) + `
PrivateTmp=true
NoNewPrivileges=true
` + capabilities + `KillSignal=SIGTERM
TimeoutStopSec=60
Restart=on-failure
RestartSec=5
LimitNOFILE=65535

[Install]
WantedBy=multi-user.target
`, nil
}

// systemdPath 转义 systemd 单值路径中的空白、反斜线和说明符。
func systemdPath(path string) string {
	return strings.NewReplacer(
		`\`, `\x5c`,
		" ", `\x20`,
		"\t", `\x09`,
		"%", "%%",
	).Replace(path)
}
