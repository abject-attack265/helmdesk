package main

import (
	"context"
	"errors"
	"flag"
	"fmt"
	"io"
	"os"
	"os/signal"
	"strings"
	"syscall"

	"helmdesk/internal/backup"
	"helmdesk/internal/deployment"
	"helmdesk/internal/localization"
	"helmdesk/internal/runtime"
	"helmdesk/internal/runtimeconfig"
)

type runtimeFlags struct {
	publicURL      *string
	tlsMode        *string
	httpAddress    *string
	httpsAddress   *string
	acmeEmail      *string
	trustedProxies *string
	storagePath    *string
}

func main() {
	language, arguments, err := extractLanguageArgument(os.Args[1:])
	if err == nil {
		err = localization.Configure(language)
	}
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		os.Exit(2)
	}

	os.Exit(run(arguments))
}

// run 执行单次 CLI 命令并返回退出码。
func run(arguments []string) int {
	if len(arguments) == 0 {
		printUsage(os.Stdout)
		return 0
	}
	if arguments[0] == "help" || arguments[0] == "--help" || arguments[0] == "-h" {
		printUsage(os.Stdout)
		return 0
	}
	if handled, exitCode := runPlatformCommand(arguments); handled {
		return exitCode
	}

	switch arguments[0] {
	case "serve":
		return runServe(arguments[1:])
	case "version":
		return runVersion(arguments[1:])
	case "backup":
		return runBackup(arguments[1:])
	case "restore":
		return runRestore(arguments[1:])
	case "artisan":
		if len(arguments) < 2 {
			fmt.Fprintln(os.Stderr, localization.Text("usage: helmdesk artisan <command> [args...]"))
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

		return runtime.RunArtisan(arguments[1:], config)
	default:
		fmt.Fprintf(os.Stderr, localization.Text("unknown command: %s\n\n"), arguments[0])
		printUsage(os.Stderr)
		return 1
	}
}

// extractLanguageArgument 只解析命令前的全局语言参数。
func extractLanguageArgument(arguments []string) (string, []string, error) {
	if len(arguments) == 0 {
		return "", arguments, nil
	}

	var value string
	var consumed int
	switch {
	case arguments[0] == "--lang":
		if len(arguments) < 2 {
			return "", nil, errors.New("the --lang command-line option requires a language value")
		}
		value = strings.TrimSpace(arguments[1])
		consumed = 2
	case strings.HasPrefix(arguments[0], "--lang="):
		value = strings.TrimSpace(strings.TrimPrefix(arguments[0], "--lang="))
		consumed = 1
	default:
		return "", arguments, nil
	}
	if value == "" {
		return "", nil, errors.New("the --lang command-line option requires a language value")
	}

	return value, arguments[consumed:], nil
}

// runServe 读取运行参数并以前台模式启动应用。
func runServe(arguments []string) int {
	flags := flag.NewFlagSet("serve", flag.ContinueOnError)
	flags.SetOutput(os.Stderr)
	configPath := flags.String("config", "", localization.Text("runtime configuration file"))
	runtimeFlags := registerRuntimeFlags(flags)
	if err := flags.Parse(arguments); err != nil {
		if errors.Is(err, flag.ErrHelp) {
			return 0
		}
		return 2
	}
	if flags.NArg() != 0 {
		fmt.Fprintf(os.Stderr, localization.Text("serve does not accept positional arguments: %s\n"), flags.Arg(0))
		return 2
	}
	config, err := installedConfig(*configPath)
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		return 1
	}
	config = applyEnvironment(config)
	config = runtimeFlags.apply(config)
	config, err = normalizeRuntimeConfig(config)
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		return 1
	}

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	if err := runtime.Run(ctx, config); err != nil {
		fmt.Fprintln(os.Stderr, err)
		return 1
	}

	return 0
}

// runBackup 解析备份参数并生成业务数据归档。
func runBackup(arguments []string) int {
	flags := flag.NewFlagSet("backup", flag.ContinueOnError)
	flags.SetOutput(os.Stderr)
	configPath := flags.String("config", "", localization.Text("runtime configuration file"))
	storagePath := flags.String("storage-path", "", localization.Text("data storage directory"))
	outputPath := flags.String("output", "", localization.Text("backup file or existing directory"))
	if err := flags.Parse(arguments); err != nil {
		if errors.Is(err, flag.ErrHelp) {
			return 0
		}
		return 2
	}
	if flags.NArg() != 0 {
		fmt.Fprintf(os.Stderr, localization.Text("backup does not accept positional arguments: %s\n"), flags.Arg(0))
		return 2
	}
	config, err := dataCommandConfig(*configPath, *storagePath)
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		return 1
	}
	result, err := backup.Create(backup.CreateOptions{
		StoragePath: config.StoragePath,
		OutputPath:  *outputPath,
		SnapshotDatabases: func(mainDestination, ragDestination string) error {
			return runtime.SnapshotDatabases(config, mainDestination, ragDestination)
		},
	})
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		return 1
	}
	fmt.Printf(localization.Text("backup created: %s (%d bytes)\n"), result.Path, result.Size)
	return 0
}

// runRestore 解析恢复参数并离线替换业务数据。
func runRestore(arguments []string) int {
	flags := flag.NewFlagSet("restore", flag.ContinueOnError)
	flags.SetOutput(os.Stderr)
	configPath := flags.String("config", "", localization.Text("runtime configuration file"))
	storagePath := flags.String("storage-path", "", localization.Text("data storage directory"))
	confirmed := flags.Bool("yes", false, localization.Text("confirm restore and replacement of target business data"))
	if err := flags.Parse(arguments); err != nil {
		if errors.Is(err, flag.ErrHelp) {
			return 0
		}
		return 2
	}
	if flags.NArg() != 1 {
		fmt.Fprintln(os.Stderr, localization.Text("usage: helmdesk restore --yes [--config file] [--storage-path directory] <backup-file>"))
		return 2
	}
	if !*confirmed {
		fmt.Fprintln(os.Stderr, localization.Text("restore replaces the target business data; add --yes to confirm"))
		return 2
	}
	config, err := dataCommandConfig(*configPath, *storagePath)
	if err != nil {
		fmt.Fprintln(os.Stderr, err)
		return 1
	}
	result, err := backup.Restore(backup.RestoreOptions{
		StoragePath: config.StoragePath,
		ArchivePath: flags.Arg(0),
		SnapshotDatabases: func(mainDestination, ragDestination string) error {
			return runtime.SnapshotDatabases(config, mainDestination, ragDestination)
		},
		PrepareInstance: func() error {
			return runtime.PrepareRestoredInstance(config)
		},
	})
	if err != nil {
		if result.SafetyBackupPath != "" {
			fmt.Fprintf(os.Stderr, localization.Text("pre-restore safety backup: %s\n"), result.SafetyBackupPath)
		}
		fmt.Fprintln(os.Stderr, err)
		return 1
	}
	fmt.Printf(localization.Text("backup restored: %s\n"), result.ArchivePath)
	if result.SafetyBackupPath != "" {
		fmt.Printf(localization.Text("pre-restore safety backup: %s\n"), result.SafetyBackupPath)
	}
	return 0
}

// dataCommandConfig 解析离线数据命令使用的运行目录。
func dataCommandConfig(configPath, storagePath string) (runtimeconfig.Config, error) {
	config, err := installedConfig(configPath)
	if err != nil {
		return runtimeconfig.Config{}, err
	}
	config = applyEnvironment(config)
	if storagePath != "" {
		config.StoragePath = storagePath
	}
	config, err = normalizeRuntimeConfig(config)
	if err != nil {
		return runtimeconfig.Config{}, err
	}
	config.StoragePath, err = runtime.ResolveStoragePath(config)
	if err != nil {
		return runtimeconfig.Config{}, fmt.Errorf(localization.Text("failed to resolve data storage directory: %w"), err)
	}
	return config, nil
}

// registerRuntimeFlags 注册运行时 CLI 参数。
func registerRuntimeFlags(flags *flag.FlagSet) runtimeFlags {
	return runtimeFlags{
		publicURL:      flags.String("public-url", "", localization.Text("canonical URL used by browsers and external systems")),
		tlsMode:        flags.String("tls-mode", "", localization.Text("TLS mode: plain, managed, or external")),
		httpAddress:    flags.String("http-address", "", localization.Text("HTTP or ACME challenge listen address")),
		httpsAddress:   flags.String("https-address", "", localization.Text("managed HTTPS listen address")),
		acmeEmail:      flags.String("acme-email", "", localization.Text("ACME account contact email")),
		trustedProxies: flags.String("trusted-proxies", "", localization.Text("trusted proxy IP/CIDR values for external mode, comma-separated")),
		storagePath:    flags.String("storage-path", "", localization.Text("data storage directory")),
	}
}

// apply 将非空命令行参数覆盖到运行配置。
func (values runtimeFlags) apply(config runtimeconfig.Config) runtimeconfig.Config {
	if *values.publicURL != "" {
		config.PublicURL = *values.publicURL
	}
	if *values.tlsMode != "" {
		config.TLSMode = runtimeconfig.TLSMode(*values.tlsMode)
	}
	if *values.httpAddress != "" {
		config.HTTPAddress = *values.httpAddress
	}
	if *values.httpsAddress != "" {
		config.HTTPSAddress = *values.httpsAddress
	}
	if *values.acmeEmail != "" {
		config.ACMEEmail = *values.acmeEmail
	}
	if *values.trustedProxies != "" {
		config.TrustedProxies = splitList(*values.trustedProxies)
	}
	if *values.storagePath != "" {
		config.StoragePath = *values.storagePath
	}

	return config
}

// applyEnvironment 使用 HELMDESK 环境变量覆盖运行配置。
func applyEnvironment(config runtimeconfig.Config) runtimeconfig.Config {
	if value := os.Getenv("HELMDESK_PUBLIC_URL"); value != "" {
		config.PublicURL = value
	}
	if value := os.Getenv("HELMDESK_TLS_MODE"); value != "" {
		config.TLSMode = runtimeconfig.TLSMode(value)
	}
	if value := os.Getenv("HELMDESK_HTTP_ADDRESS"); value != "" {
		config.HTTPAddress = value
	}
	if value := os.Getenv("HELMDESK_HTTPS_ADDRESS"); value != "" {
		config.HTTPSAddress = value
	}
	if value := os.Getenv("HELMDESK_ACME_EMAIL"); value != "" {
		config.ACMEEmail = value
	}
	if value := os.Getenv("HELMDESK_TRUSTED_PROXIES"); value != "" {
		config.TrustedProxies = splitList(value)
	}
	if value := os.Getenv("HELMDESK_STORAGE_PATH"); value != "" {
		config.StoragePath = value
	}

	return config
}

// normalizeRuntimeConfig 补全并校验运行配置。
func normalizeRuntimeConfig(config runtimeconfig.Config) (runtimeconfig.Config, error) {
	normalized, err := runtimeconfig.Normalize(config)
	if err != nil {
		return runtimeconfig.Config{}, fmt.Errorf(localization.Text("invalid runtime configuration: %w"), err)
	}

	return normalized, nil
}

// splitList 解析逗号分隔的配置值。
func splitList(value string) []string {
	return strings.Split(value, ",")
}

// installedConfig 读取显式配置、系统安装配置或平台默认值。
func installedConfig(path string) (runtimeconfig.Config, error) {
	if path != "" {
		config, err := deployment.LoadConfig(path)
		if errors.Is(err, os.ErrNotExist) {
			return runtimeconfig.Config{}, fmt.Errorf(localization.Text("runtime configuration does not exist: %s"), path)
		}

		return config, err
	}

	path = deployment.ConfigPath()
	if path == "" {
		return deployment.DefaultRuntimeConfig(), nil
	}
	config, err := deployment.LoadConfig(path)
	if errors.Is(err, os.ErrNotExist) {
		return deployment.DefaultRuntimeConfig(), nil
	}

	return config, err
}

// printUsage 将命令行帮助写入指定输出。
func printUsage(output io.Writer) {
	fmt.Fprint(output, localization.Text(`Usage:
  helmdesk [--lang en|zh-CN] <command> [options]

Commands:
  helmdesk serve [--config file] [runtime options]
  helmdesk backup [--config file] [--storage-path directory] [--output file-or-directory]
  helmdesk restore --yes [--config file] [--storage-path directory] <backup-file>
  helmdesk artisan <command> [args...]
  helmdesk version [--json]
  helmdesk help
`))
	fmt.Fprint(output, platformUsage())
	fmt.Fprint(output, localization.Text(`
Global options:
  --lang en|zh-CN  Output language (default: en; environment: HELMDESK_LANG)

Runtime options:
  --public-url URL
  --tls-mode plain|managed|external
  --http-address address
  --https-address address
  --acme-email email
  --trusted-proxies IP/CIDR,...
  --storage-path directory
`))
}
