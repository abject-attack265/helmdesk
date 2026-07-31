package runtime

import (
	"context"
	"errors"
	"fmt"
	"log/slog"
	"maps"
	"net/http"
	"os"
	"path/filepath"
	"runtime"
	"slices"
	"strings"
	"time"

	"helmdesk/internal/localization"
	"helmdesk/internal/runtime/sqlitevec"
	"helmdesk/internal/runtimeconfig"
	"helmdesk/internal/storageguard"

	"github.com/dunglas/frankenphp"
	"github.com/dunglas/mercure"
	"github.com/robfig/cron/v3"
)

// Run 初始化应用运行时并持续服务到上下文取消。
func Run(ctx context.Context, options runtimeconfig.Config) error {
	storagePath, err := ResolveStoragePath(options)
	if err != nil {
		return fmt.Errorf(localization.Text("failed to resolve runtime directory: %w"), err)
	}
	storageLock, err := storageguard.Acquire(storagePath)
	if err != nil {
		return fmt.Errorf(localization.Text("failed to lock runtime directory: %w"), err)
	}
	defer func() {
		if err := storageLock.Close(); err != nil {
			slog.Warn(localization.Text("failed to release runtime directory lock"), "storage_path", storagePath, "error", err)
		}
	}()
	options.StoragePath = storagePath

	cfg, err := newConfig(options)
	if err != nil {
		return fmt.Errorf(localization.Text("failed to load runtime configuration: %w"), err)
	}
	slog.Info(
		localization.Text("starting HelmDesk"),
		"public_url", cfg.runtimeConfig.PublicURL,
		"tls_mode", cfg.runtimeConfig.TLSMode,
		"storage_path", cfg.storagePath,
	)
	if err := sqlitevec.Register(cfg.projectRoot); err != nil {
		return fmt.Errorf(localization.Text("failed to load sqlite-vec: %w"), err)
	}
	if frankenphp.EmbeddedAppPath != "" {
		slog.Info(localization.Text("preparing Laravel application"))
		if runCLI(cfg, []string{"config:clear"}) != 0 {
			return errors.New(localization.Text("failed to clear Laravel configuration cache"))
		}
		if runCLI(cfg, []string{"migrate", "--force"}) != 0 {
			return errors.New(localization.Text("database migration failed"))
		}
		if runCLI(cfg, []string{"optimize"}) != 0 {
			return errors.New(localization.Text("Laravel optimization failed"))
		}
	}

	runtimeCtx, cancel := context.WithCancel(ctx)
	defer cancel()

	hub, err := newMercureHub(runtimeCtx, cfg)
	if err != nil {
		return fmt.Errorf(localization.Text("failed to start Mercure: %w"), err)
	}
	queuePools, artisanWorkers, workerOptions, err := registerWorkers(cfg, hub)
	if err != nil {
		return fmt.Errorf(localization.Text("failed to register PHP workers: %w"), err)
	}
	workerOptions = append(workerOptions, frankenphp.WithPhpIni(phpIni(cfg.storagePath)))
	if err := frankenphp.Init(workerOptions...); err != nil {
		return fmt.Errorf(localization.Text("failed to start FrankenPHP: %w"), err)
	}
	defer func() {
		frankenphp.Shutdown()
		slog.Info(localization.Text("HelmDesk runtime has stopped"))
	}()

	startQueueLoops(runtimeCtx, queuePools)
	scheduler, err := startScheduler(runtimeCtx, artisanWorkers)
	if err != nil {
		return fmt.Errorf(localization.Text("failed to start scheduler: %w"), err)
	}

	mux := http.NewServeMux()
	mux.Handle("/.well-known/mercure", hub)
	mux.Handle("/", limitLocalUploadBody(phpHandler(cfg, hub)))
	slog.Info(localization.Text("HelmDesk workers are ready"), "queue_pools", len(queuePools), "queue_workers", totalQueueWorkers())
	runErr := serveHTTP(ctx, cfg, mux, cancel)
	cancel()
	<-scheduler.Stop().Done()
	shutdownCtx, shutdownCancel := context.WithTimeout(context.Background(), 30*time.Second)
	if err := hub.Stop(shutdownCtx); err != nil {
		runErr = errors.Join(runErr, fmt.Errorf(localization.Text("Mercure failed to stop: %w"), err))
	}
	shutdownCancel()
	return runErr
}

// newMercureHub 创建随运行时 context 关闭的内存 Mercure Hub。
func newMercureHub(ctx context.Context, cfg *config) (*mercure.Hub, error) {
	publisher := cfg.value("MERCURE_PUBLISHER_JWT")
	subscriber := cfg.value("MERCURE_SUBSCRIBER_JWT")
	if publisher == "" {
		return nil, errors.New(localization.Text("MERCURE_PUBLISHER_JWT cannot be empty"))
	}
	if subscriber == "" {
		return nil, errors.New(localization.Text("MERCURE_SUBSCRIBER_JWT cannot be empty"))
	}
	subscribers := mercure.NewSubscriberList(1000)
	return mercure.NewHub(
		ctx,
		mercure.WithAnonymous(),
		mercure.WithPublisherJWT([]byte(publisher), "HS256"),
		mercure.WithSubscriberJWT([]byte(subscriber), "HS256"),
		mercure.WithTransport(mercure.NewLocalTransport(subscribers)),
		mercure.WithHeartbeat(40*time.Second),
		// SSE 请求随 Hub context 结束，HTTP 服务可以等待订阅正常注销。
		mercure.WithWriteTimeout(0),
	)
}

type queueSpec struct {
	name    string
	workers int
}

type queuePool struct {
	name    string
	workers frankenphp.Workers
}

var queueSpecs = []queueSpec{
	{name: "reception-buffer", workers: 2},
	{name: "interactive-ai", workers: 8},
	{name: "background", workers: 2},
	{name: "knowledge", workers: 2},
	{name: "search-index", workers: 1},
	{name: "channel-inbound", workers: 2},
	{name: "channel-outbound", workers: 2},
	{name: "notifications", workers: 1},
}

// totalQueueWorkers 统计所有队列池的并发 worker 数量。
func totalQueueWorkers() int {
	total := 0
	for _, spec := range queueSpecs {
		total += spec.workers
	}
	return total
}

// registerWorkers 注册 Web、Artisan 和队列 worker。
func registerWorkers(cfg *config, hub *mercure.Hub) ([]queuePool, frankenphp.Workers, []frankenphp.Option, error) {
	base := []frankenphp.WorkerOption{
		frankenphp.WithWorkerEnv(cfg.phpEnv),
		frankenphp.WithWorkerMaxFailures(0),
		frankenphp.WithWorkerMercureHub(hub),
	}
	webOptions := slices.Clone(base)
	if frankenphp.EmbeddedAppPath == "" {
		webOptions = append(webOptions, frankenphp.WithWorkerWatchMode([]string{
			filepath.Join(cfg.projectRoot, "app"), filepath.Join(cfg.projectRoot, "bootstrap"),
			filepath.Join(cfg.projectRoot, "config"), filepath.Join(cfg.projectRoot, "routes"),
		}))
	}

	consoleOptions := slices.Clone(base)
	consoleEnv := maps.Clone(cfg.phpEnv)
	consoleEnv["APP_RUNNING_IN_CONSOLE"] = "true"
	consoleOptions = append(consoleOptions, frankenphp.WithWorkerEnv(consoleEnv))
	artisanWorkers, artisanOption := frankenphp.WithExtensionWorkers("artisan", filepath.Join(cfg.projectRoot, "public", "artisan-worker.php"), 1, slices.Clone(consoleOptions)...)
	options := []frankenphp.Option{
		frankenphp.WithWorkers("web", filepath.Join(cfg.projectRoot, "public", "frankenphp-worker.php"), runtime.NumCPU()*2, webOptions...),
		artisanOption,
	}
	queuePools := make([]queuePool, 0, len(queueSpecs))
	for _, spec := range queueSpecs {
		workerPath, err := queueWorkerPath(cfg, spec.name)
		if err != nil {
			return nil, nil, nil, err
		}
		workers, option := frankenphp.WithExtensionWorkers("queue-"+spec.name, workerPath, spec.workers, slices.Clone(consoleOptions)...)
		queuePools = append(queuePools, queuePool{name: spec.name, workers: workers})
		options = append(options, option)
	}
	return queuePools, artisanWorkers, options, nil
}

// queueWorkerPath 为队列池生成独立的 PHP worker 入口文件。
func queueWorkerPath(cfg *config, name string) (string, error) {
	path := filepath.Join(cfg.storagePath, "framework", "workers", "queue-"+name+".php")
	target := filepath.Join(cfg.projectRoot, "public", "queue-worker.php")
	target = strings.ReplaceAll(strings.ReplaceAll(target, `\`, `\\`), `'`, `\'`)
	contents := "<?php\n\nrequire '" + target + "';\n"
	return path, os.WriteFile(path, []byte(contents), 0644)
}

// phpIni 返回内嵌 PHP 运行时配置。
func phpIni(storagePath string) map[string]string {
	return map[string]string{
		"memory_limit": "512M", "post_max_size": "205M", "upload_max_filesize": "200M",
		"max_execution_time": "0", "opcache.file_cache": filepath.Join(storagePath, "framework", "cache", "opcache"),
	}
}

// startQueueLoops 为每个队列 worker 启动持续消费循环。
func startQueueLoops(ctx context.Context, pools []queuePool) {
	for _, pool := range pools {
		for range pool.workers.NumThreads() {
			go func(pool queuePool) {
				for ctx.Err() == nil {
					requestCtx, cancel := context.WithTimeout(ctx, 1210*time.Second)
					response, err := pool.workers.SendMessage(requestCtx, map[string]any{"queue": pool.name}, nil)
					cancel()
					if err != nil {
						if ctx.Err() != nil {
							return
						}
						slog.Error(localization.Text("queue task failed"), "queue", pool.name, "error", err)
						time.Sleep(time.Second)
						continue
					}
					processed, err := queueResponseProcessed(response)
					if err != nil {
						slog.Error(localization.Text("queue worker returned an invalid result"), "queue", pool.name, "error", err)
						time.Sleep(time.Second)
						continue
					}
					if !processed {
						time.Sleep(250 * time.Millisecond)
					}
				}
			}(pool)
		}
	}
}

// queueResponseProcessed 读取 PHP 队列 worker 的处理结果。
func queueResponseProcessed(response any) (bool, error) {
	result, ok := response.(frankenphp.AssociativeArray[any])
	if !ok {
		return false, fmt.Errorf(localization.Text("return value has type %T"), response)
	}
	processed, ok := result.Map["processed"].(bool)
	if !ok {
		return false, errors.New(localization.Text("the processed field is missing or has an invalid type"))
	}
	return processed, nil
}

type scheduledCommand struct {
	name       string
	parameters map[string]any
}

type scheduledBatch struct {
	expression string
	commands   []scheduledCommand
}

var scheduledBatches = []scheduledBatch{
	{
		expression: "* * * * *",
		commands: []scheduledCommand{
			{name: "outbox:reconcile"},
			{name: "wechat-inbound:reconcile"},
			{name: "telegram-inbound:reconcile"},
			// 同一分钟内到期的会话先交给 AI，再判断是否需要空闲关单。
			{name: "reception:take-over-overdue-conversations"},
			{name: "reception:close-idle-conversations"},
			{name: "teammates:offline-inactive"},
		},
	},
	{
		expression: "0 * * * *",
		commands:   []scheduledCommand{{name: "attachments:cleanup"}},
	},
	{
		expression: "0 0 * * *",
		commands: []scheduledCommand{{
			name:       "model:prune",
			parameters: map[string]any{"--model": []any{"App\\Models\\AiCallLog"}},
		}},
	},
}

// cronLogger 将调度器内部事件映射到应用日志级别。
type cronLogger struct{}

// Info 将任务重叠记为警告，其余事件记为调试日志。
func (cronLogger) Info(message string, keysAndValues ...any) {
	if message == "skip" {
		slog.Warn(localization.Text("scheduled task is still running; skipped this invocation"), keysAndValues...)
		return
	}
	attributes := append([]any{"event", message}, keysAndValues...)
	slog.Debug(localization.Text("scheduler event"), attributes...)
}

// Error 记录调度器错误和原始事件名称。
func (cronLogger) Error(err error, message string, keysAndValues ...any) {
	attributes := append([]any{"event", message, "error", err}, keysAndValues...)
	slog.Error(localization.Text("scheduler error"), attributes...)
}

// startScheduler 使用 UTC cron 表达式注册并启动定时任务。
func startScheduler(ctx context.Context, workers frankenphp.Workers) (*cron.Cron, error) {
	logger := cronLogger{}
	scheduler := cron.New(
		cron.WithLocation(time.UTC),
		cron.WithChain(cron.SkipIfStillRunning(logger)),
	)
	for _, batch := range scheduledBatches {
		if _, err := scheduler.AddFunc(batch.expression, func() {
			runScheduledCommands(ctx, workers, batch.commands)
		}); err != nil {
			return nil, fmt.Errorf(localization.Text("failed to register cron expression %q: %w"), batch.expression, err)
		}
	}
	scheduler.Start()
	slog.Info(localization.Text("scheduler started"), "timezone", "UTC", "schedules", len(scheduledBatches))
	return scheduler, nil
}

// runScheduledCommands 串行执行一组 Artisan 命令。
func runScheduledCommands(ctx context.Context, workers frankenphp.Workers, commands []scheduledCommand) {
	for _, command := range commands {
		if ctx.Err() != nil {
			return
		}
		response, err := workers.SendMessage(ctx, map[string]any{
			"command":    command.name,
			"parameters": command.parameters,
		}, nil)
		if err != nil {
			if ctx.Err() != nil {
				return
			}
			slog.Error(localization.Text("scheduled task failed"), "command", command.name, "error", err)
			continue
		}
		result := response.(frankenphp.AssociativeArray[any])
		exitCode := result.Map["exit_code"].(int64)
		if exitCode != 0 {
			slog.Error(
				localization.Text("scheduled task failed"),
				"command", command.name,
				"exit_code", exitCode,
				"output", result.Map["output"].(string),
			)
		}
	}
}
