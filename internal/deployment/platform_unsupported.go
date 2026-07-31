//go:build !linux && !windows

package deployment

import "helmdesk/internal/runtimeconfig"

// ConfigPath 返回空路径，当前平台以前台默认配置运行。
func ConfigPath() string {
	return ""
}

// DefaultRuntimeConfig 返回本机明文前台运行配置。
func DefaultRuntimeConfig() runtimeconfig.Config {
	return runtimeconfig.Default()
}
