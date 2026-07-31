package runtimeconfig

import (
	"errors"
	"fmt"
	"net"
	"net/mail"
	"net/url"
	"strconv"
	"strings"

	"helmdesk/internal/localization"
)

// TLSMode 描述公网请求的 TLS 终止方式。
type TLSMode string

const (
	// TLSModePlain 表示应用直接提供明文 HTTP。
	TLSModePlain TLSMode = "plain"
	// TLSModeManaged 表示应用通过 ACME 自动管理并终止 TLS。
	TLSModeManaged TLSMode = "managed"
	// TLSModeExternal 表示可信反向代理终止 TLS，应用接收其明文转发。
	TLSModeExternal TLSMode = "external"
)

// Config 保存平台安装器与应用运行时共享的服务配置。
type Config struct {
	// PublicURL 是浏览器和外部系统使用的规范地址。
	PublicURL string `json:"public_url"`
	// TLSMode 指定 TLS 的终止位置。
	TLSMode TLSMode `json:"tls_mode"`
	// HTTPAddress 是明文应用入口或 ACME challenge 入口的监听地址。
	HTTPAddress string `json:"http_address"`
	// HTTPSAddress 是托管 TLS 模式的 HTTPS 监听地址。
	HTTPSAddress string `json:"https_address,omitempty"`
	// ACMEEmail 是 ACME 账户使用的可选联系邮箱。
	ACMEEmail string `json:"acme_email,omitempty"`
	// TrustedProxies 是 external 模式允许提供转发头的代理地址。
	TrustedProxies []string `json:"trusted_proxies,omitempty"`
	// StoragePath 是 Laravel 配置、证书和业务数据目录。
	StoragePath string `json:"storage_path"`
	hostname    string
}

// Default 返回本机明文运行配置。
func Default() Config {
	return Config{
		PublicURL: "http://127.0.0.1:8080",
		TLSMode:   TLSModePlain,
	}
}

// Normalize 补全模式默认监听地址并校验服务配置。
func Normalize(input Config) (Config, error) {
	config := input
	config.PublicURL = strings.TrimSpace(config.PublicURL)
	config.TLSMode = TLSMode(strings.TrimSpace(string(config.TLSMode)))
	config.HTTPAddress = strings.TrimSpace(config.HTTPAddress)
	config.HTTPSAddress = strings.TrimSpace(config.HTTPSAddress)
	config.ACMEEmail = strings.TrimSpace(config.ACMEEmail)

	if config.PublicURL == "" {
		return Config{}, errors.New(localization.Text("public_url cannot be empty"))
	}
	if config.TLSMode == "" {
		return Config{}, errors.New(localization.Text("tls_mode cannot be empty"))
	}
	if config.HTTPAddress == "" {
		if config.TLSMode == TLSModeManaged {
			config.HTTPAddress = "0.0.0.0:80"
		} else {
			config.HTTPAddress = "127.0.0.1:8080"
		}
	}
	if config.TLSMode == TLSModeManaged && config.HTTPSAddress == "" {
		config.HTTPSAddress = "0.0.0.0:443"
	}

	publicURL, err := normalizePublicURL(config.PublicURL)
	if err != nil {
		return Config{}, err
	}
	config.PublicURL = publicURL.String()
	config.hostname = publicURL.Hostname()

	if err := validateListenAddress("http_address", config.HTTPAddress); err != nil {
		return Config{}, err
	}
	if config.HTTPSAddress != "" {
		if err := validateListenAddress("https_address", config.HTTPSAddress); err != nil {
			return Config{}, err
		}
	}
	if err := validateMode(config, publicURL); err != nil {
		return Config{}, err
	}
	if config.ACMEEmail != "" {
		address, parseErr := mail.ParseAddress(config.ACMEEmail)
		if parseErr != nil || address.Address != config.ACMEEmail {
			return Config{}, errors.New(localization.Text("acme_email must be a valid email address"))
		}
	}

	trustedProxies, err := normalizeTrustedProxies(config.TrustedProxies)
	if err != nil {
		return Config{}, err
	}
	config.TrustedProxies = trustedProxies
	if config.TLSMode == TLSModeExternal && len(config.TrustedProxies) == 0 {
		return Config{}, errors.New(localization.Text("external TLS mode requires trusted_proxies"))
	}
	if config.TLSMode != TLSModeExternal && len(config.TrustedProxies) > 0 {
		return Config{}, errors.New(localization.Text("trusted_proxies is only valid in external TLS mode"))
	}

	return config, nil
}

// Hostname 返回 Normalize 解析出的规范公网域名。
func (config Config) Hostname() string {
	return config.hostname
}

// normalizePublicURL 校验并规范化应用对外地址。
func normalizePublicURL(value string) (*url.URL, error) {
	publicURL, err := url.Parse(value)
	if err != nil {
		return nil, fmt.Errorf(localization.Text("invalid public_url: %w"), err)
	}
	if publicURL.Scheme != "http" && publicURL.Scheme != "https" {
		return nil, errors.New(localization.Text("public_url only supports http or https"))
	}
	if publicURL.Hostname() == "" {
		return nil, errors.New(localization.Text("public_url must contain a hostname"))
	}
	if publicURL.User != nil || publicURL.RawQuery != "" || publicURL.Fragment != "" {
		return nil, errors.New(localization.Text("public_url cannot contain user information, query parameters, or a fragment"))
	}
	if publicURL.Path != "" && publicURL.Path != "/" {
		return nil, errors.New(localization.Text("public_url cannot contain a path"))
	}
	hostname := strings.ToLower(strings.TrimSuffix(publicURL.Hostname(), "."))
	if hostname == "" {
		return nil, errors.New(localization.Text("public_url must contain a valid hostname"))
	}
	if port := publicURL.Port(); port != "" {
		publicURL.Host = net.JoinHostPort(hostname, port)
	} else if strings.Contains(hostname, ":") {
		publicURL.Host = "[" + hostname + "]"
	} else {
		publicURL.Host = hostname
	}
	publicURL.Path = ""

	return publicURL, nil
}

// validateMode 校验 TLS 模式与公网 URL、监听地址之间的约束。
func validateMode(config Config, publicURL *url.URL) error {
	switch config.TLSMode {
	case TLSModePlain:
		if publicURL.Scheme != "http" {
			return errors.New(localization.Text("plain TLS mode requires an http public_url"))
		}
		if config.ACMEEmail != "" {
			return errors.New(localization.Text("acme_email is only valid in managed TLS mode"))
		}
	case TLSModeManaged:
		if publicURL.Scheme != "https" {
			return errors.New(localization.Text("managed TLS mode requires an https public_url"))
		}
		if publicURL.Port() != "" {
			return errors.New(localization.Text("managed TLS mode does not allow a port in public_url"))
		}
		if config.HTTPSAddress == "" {
			return errors.New(localization.Text("managed TLS mode requires https_address"))
		}
		if config.HTTPAddress == config.HTTPSAddress {
			return errors.New(localization.Text("http_address and https_address must differ in managed TLS mode"))
		}
		hostname := publicURL.Hostname()
		if net.ParseIP(hostname) != nil || strings.EqualFold(hostname, "localhost") || !strings.Contains(hostname, ".") {
			return errors.New(localization.Text("managed TLS mode requires a public domain name in public_url"))
		}
		if strings.IndexFunc(hostname, func(character rune) bool { return character > 127 }) >= 0 {
			return errors.New(localization.Text("internationalized domain names must use ASCII Punycode in managed TLS mode"))
		}
	case TLSModeExternal:
		if publicURL.Scheme != "https" {
			return errors.New(localization.Text("external TLS mode requires an https public_url"))
		}
		if publicURL.Port() != "" {
			return errors.New(localization.Text("external TLS mode does not allow a port in public_url"))
		}
		if config.ACMEEmail != "" {
			return errors.New(localization.Text("acme_email is only valid in managed TLS mode"))
		}
	default:
		return fmt.Errorf(localization.Text("unsupported tls_mode: %s"), config.TLSMode)
	}

	return nil
}

// validateListenAddress 校验 TCP 监听地址和端口范围。
func validateListenAddress(name, value string) error {
	_, port, err := net.SplitHostPort(value)
	if err != nil {
		return fmt.Errorf(localization.Text("%s must use the host:port format: %w"), name, err)
	}
	number, err := strconv.Atoi(port)
	if err != nil || number < 1 || number > 65535 {
		return fmt.Errorf(localization.Text("%s port must be between 1 and 65535"), name)
	}

	return nil
}

// normalizeTrustedProxies 校验、去重并规范化可信代理地址。
func normalizeTrustedProxies(values []string) ([]string, error) {
	proxies := make([]string, 0, len(values))
	seen := make(map[string]struct{}, len(values))
	for _, value := range values {
		proxy := strings.TrimSpace(value)
		if proxy == "" {
			return nil, errors.New(localization.Text("trusted_proxies cannot contain empty values"))
		}
		if proxy != "*" && net.ParseIP(proxy) == nil {
			if _, _, err := net.ParseCIDR(proxy); err != nil {
				return nil, fmt.Errorf(localization.Text("trusted_proxies contains an invalid IP address or CIDR: %s"), proxy)
			}
		}
		if _, exists := seen[proxy]; exists {
			continue
		}
		seen[proxy] = struct{}{}
		proxies = append(proxies, proxy)
	}
	if _, wildcard := seen["*"]; wildcard && len(proxies) > 1 {
		return nil, errors.New(localization.Text("trusted_proxies cannot contain other addresses when * is used"))
	}

	return proxies, nil
}
