package localization

var simplifiedChineseMessages = map[string]string{
	`  helmdesk install [runtime options]
  helmdesk upgrade [--check]
  helmdesk start
  helmdesk stop
  helmdesk restart
  helmdesk status
  helmdesk logs [--follow] [--lines count] [--since time]
  helmdesk uninstall
`: `  helmdesk install [运行参数]
  helmdesk upgrade [--check]
  helmdesk start
  helmdesk stop
  helmdesk restart
  helmdesk status
  helmdesk logs [--follow] [--lines 行数] [--since 时间]
  helmdesk uninstall
`,
	`  helmdesk upgrade [--check]
  helmdesk status
`: `  helmdesk upgrade [--check]
  helmdesk status
`,
	"%s cannot listen on %s: %w":                                "%s 无法监听 %s: %w",
	"%s contains invalid characters":                            "%s 包含无效字符",
	"%s failed: %w":                                             "%s 执行失败: %w",
	"%s line %d is missing a configuration name":                "%s 第 %d 行缺少配置名称",
	"%s line %d is missing an equals sign":                      "%s 第 %d 行缺少等号",
	"%s listener failed to stop: %w":                            "%s 监听器停止失败: %w",
	"%s must use the host:port format: %w":                      "%s 必须是 host:port 格式: %w",
	"%s port must be between 1 and 65535":                       "%s 端口必须在 1 到 65535 之间",
	"%s server exited unexpectedly: %w":                         "%s 服务异常退出: %w",
	"%s server failed to stop: %w":                              "%s 服务停止失败: %w",
	"ACME account contact email":                                "ACME 账户联系邮箱",
	"HTTP or ACME challenge listen address":                     "HTTP 或 ACME challenge 监听地址",
	"HelmDesk has been installed and started":                   "HelmDesk 服务已安装并启动",
	"HelmDesk has been uninstalled; business data was retained": "HelmDesk 服务已卸载，业务数据已保留",
	"HelmDesk health check failed: %s returned %s":              "HelmDesk 状态异常: %s 返回 %s",
	"HelmDesk is not running (%s): %w":                          "HelmDesk 未运行 (%s): %w",
	"HelmDesk is running: %s\n":                                 "HelmDesk 正在运行: %s\n",
	"HelmDesk runtime has stopped":                              "HelmDesk 运行时已停止",
	"HelmDesk service operation completed":                      "HelmDesk 服务操作完成",
	"HelmDesk systemd unit was not found; cleaning up the program and runtime configuration": "未找到 HelmDesk systemd 服务单元，将清理程序和运行配置",
	"HelmDesk workers are ready":                  "HelmDesk Worker 已就绪",
	"Laravel optimization failed":                 "Laravel 优化失败",
	"MERCURE_PUBLISHER_JWT cannot be empty":       "MERCURE_PUBLISHER_JWT 不能为空",
	"MERCURE_SUBSCRIBER_JWT cannot be empty":      "MERCURE_SUBSCRIBER_JWT 不能为空",
	"Mercure failed to stop: %w":                  "Mercure 停止失败: %w",
	"PHP request failed":                          "PHP 请求执行失败",
	"SQLite snapshot command exited with code %d": "SQLite 快照命令退出码为 %d",
	"SQLite snapshot has an invalid header: %s":   "SQLite 快照文件头无效: %s",
	"TLS mode: plain, managed, or external":       "TLS 模式: plain、managed 或 external",
	`
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
`: `
全局参数:
  --lang en|zh-CN  输出语言（默认: en；环境变量: HELMDESK_LANG）

运行参数:
  --public-url URL
  --tls-mode plain|managed|external
  --http-address 地址
  --https-address 地址
  --acme-email 邮箱
  --trusted-proxies IP/CIDR,...
  --storage-path 目录
`,
	`Usage:
  helmdesk [--lang en|zh-CN] <command> [options]

Commands:
  helmdesk serve [--config file] [runtime options]
  helmdesk backup [--config file] [--storage-path directory] [--output file-or-directory]
  helmdesk restore --yes [--config file] [--storage-path directory] <backup-file>
  helmdesk artisan <command> [args...]
  helmdesk version [--json]
  helmdesk help
`: `用法:
  helmdesk [--lang en|zh-CN] <command> [options]

命令:
  helmdesk serve [--config 文件] [运行参数]
  helmdesk backup [--config 文件] [--storage-path 目录] [--output 文件或目录]
  helmdesk restore --yes [--config 文件] [--storage-path 目录] <备份文件>
  helmdesk artisan <command> [args...]
  helmdesk version [--json]
  helmdesk help
`,
	"acme_email is only valid in managed TLS mode":                                               "acme_email 仅用于 managed TLS 模式",
	"acme_email must be a valid email address":                                                   "acme_email 必须是有效邮箱地址",
	"archive contains an unsupported file type: %s":                                              "归档包含不支持的文件类型: %s",
	"attachment size exceeds the upload limit":                                                   "附件大小超过上传上限",
	"attachment uploads require Content-Length":                                                  "附件上传必须提供 Content-Length",
	"automatic HTTPS certificate cache detected":                                                 "检测到自动 HTTPS 证书缓存",
	"automatic HTTPS certificate saved":                                                          "自动 HTTPS 证书已保存",
	"automatic HTTPS certificate was not found; it will be requested when a TLS request arrives": "未找到自动 HTTPS 证书，将在 TLS 请求到达时申请",
	"backup archive validation passed":                                                           "备份归档校验通过",
	"backup contains a duplicate file: %s":                                                       "备份包含重复文件: %s",
	"backup contains a file not listed in the manifest: %s":                                      "备份包含清单外文件: %s",
	"backup contains a non-business directory: %s":                                               "备份包含非业务目录: %s",
	"backup contains an invalid path: %s":                                                        "备份包含无效路径: %s",
	"backup contains an unsupported file type: %s":                                               "备份包含不支持的文件类型: %s",
	"backup creation completed":                                                                  "备份创建完成",
	"backup created: %s (%d bytes)\n":                                                            "备份已创建: %s (%d bytes)\n",
	"backup does not accept positional arguments: %s\n":                                          "backup 不接受位置参数: %s\n",
	"backup file already exists: %s":                                                             "备份文件已存在: %s",
	"backup file cannot be empty":                                                                "备份文件不能为空",
	"backup file cannot be written inside the local business file directory":                     "备份文件不能写入本地业务文件目录",
	"backup file checksum validation failed: %s":                                                 "备份文件摘要校验失败: %s",
	"backup file or existing directory":                                                          "备份文件或现有目录",
	"backup file size does not match the manifest: %s":                                           "备份文件大小与清单不一致: %s",
	"backup is missing files listed in the manifest":                                             "备份缺少清单中的文件",
	"backup manifest contains a duplicate file: %s":                                              "备份清单包含重复文件: %s",
	"backup manifest contains a non-business file: %s":                                           "备份清单包含非业务文件: %s",
	"backup manifest contains an invalid file checksum: %s":                                      "备份清单文件摘要无效: %s",
	"backup manifest contains an invalid file size: %s":                                          "备份清单文件大小无效: %s",
	"backup manifest contains trailing content":                                                  "备份清单包含多余内容",
	"backup manifest has an invalid created_at value":                                            "备份清单 created_at 无效",
	"backup manifest is missing %s":                                                              "备份清单缺少 %s",
	"backup manifest is missing: %w":                                                             "备份缺少清单: %w",
	"backup restore completed":                                                                   "备份恢复完成",
	"backup restored: %s\n":                                                                      "备份已恢复: %s\n",
	"backup source is missing %s: %w":                                                            "备份源缺少 %s: %w",
	"backup source is not a regular file: %s":                                                    "备份源不是普通文件: %s",
	"backup validation failed: %w":                                                               "备份校验失败: %w",
	"business data replacement completed; initializing databases and search indexes":             "业务数据替换完成，正在初始化数据库和检索索引",
	"business data was restored, but setting runtime data ownership failed: %w":                  "业务数据已恢复，设置运行数据所有权失败: %w",
	"business data was restored, but transient data initialization failed: %w":                   "业务数据已恢复，初始化瞬态数据失败: %w",
	"business file directory contains a symbolic link: %s":                                       "业务文件目录包含符号链接: %s",
	"business file directory contains an unsupported file type: %s":                              "业务文件目录包含不支持的文件类型: %s",
	"canonical URL used by browsers and external systems":                                        "浏览器和外部系统使用的规范 URL",
	"confirm restore and replacement of target business data":                                    "确认恢复并替换目标业务数据",
	"creating SQLite business database snapshots":                                                "正在生成 SQLite 业务分库快照",
	"data storage directory":                                                                     "数据存储目录",
	"database migration failed":                                                                  "数据库迁移失败",
	"database snapshot implementation cannot be empty":                                           "数据库快照实现不能为空",
	"external TLS mode does not allow a port in public_url":                                      "external TLS 模式不支持 public_url 指定端口",
	"external TLS mode requires an https public_url":                                             "external TLS 模式要求 public_url 使用 https",
	"external TLS mode requires trusted_proxies":                                                 "external TLS 模式必须配置 trusted_proxies",
	"external TLS trusts all proxy sources":                                                      "外部 TLS 信任所有代理来源",
	"failed to check system user: %w":                                                            "检查系统用户失败: %w",
	"failed to check systemd status: %w":                                                         "检查 systemd 运行状态失败: %w",
	"failed to clean up backup staging directory":                                                "清理备份暂存目录失败",
	"failed to clean up incomplete backup archive":                                               "清理未完成的备份归档失败",
	"failed to clean up restore rollback directory":                                              "清理恢复回滚目录失败",
	"failed to clean up restore staging directory":                                               "清理恢复暂存目录失败",
	"failed to clear Laravel configuration cache":                                                "清理 Laravel 配置缓存失败",
	"failed to create PHP request":                                                               "创建 PHP 请求失败",
	"failed to create SQLite snapshots: %w":                                                      "创建 SQLite 快照失败: %w",
	"failed to create certificate directory: %w":                                                 "创建证书目录失败: %w",
	"failed to create restore rollback directory %s: %w":                                         "创建恢复回滚目录失败 %s: %w",
	"failed to create the pre-restore safety backup: %w":                                         "创建恢复前安全快照失败: %w",
	"failed to load Artisan runtime configuration":                                               "无法加载 Artisan 运行配置",
	"failed to load runtime configuration: %w":                                                   "无法加载运行配置: %w",
	"failed to load sqlite-vec":                                                                  "无法加载 sqlite-vec",
	"failed to load sqlite-vec: %d":                                                              "无法加载 sqlite-vec: %d",
	"failed to load sqlite-vec: %w":                                                              "无法加载 sqlite-vec: %w",
	"failed to lock runtime directory: %w":                                                       "无法锁定运行目录: %w",
	"failed to parse runtime configuration: %w":                                                  "解析运行配置失败: %w",
	"failed to read automatic HTTPS certificate cache":                                           "读取自动 HTTPS 证书缓存失败",
	"failed to read runtime directory owner":                                                     "无法读取运行目录所有者",
	"failed to read static file":                                                                 "读取静态文件失败",
	"failed to register PHP workers: %w":                                                         "无法注册 PHP Worker: %w",
	"failed to register cron expression %q: %w":                                                  "无法注册 cron 表达式 %q: %w",
	"failed to release runtime directory lock":                                                   "释放运行目录锁失败",
	"failed to remove incomplete restored data %s: %w":                                           "删除未完成的恢复数据失败 %s: %w",
	"failed to resolve data storage directory: %w":                                               "无法解析数据存储目录: %w",
	"failed to resolve runtime directory: %w":                                                    "无法解析运行目录: %w",
	"failed to restore original runtime data %s: %w":                                             "恢复原始运行数据失败 %s: %w",
	"failed to save the automatic HTTPS certificate":                                             "保存自动 HTTPS 证书失败",
	"failed to set Artisan environment variable":                                                 "无法设置 Artisan 环境变量",
	"failed to set certificate directory permissions: %w":                                        "设置证书目录权限失败: %w",
	"failed to set restored data ownership: %w":                                                  "设置恢复数据所有权失败: %w",
	"failed to stage existing runtime data: %w":                                                  "暂存现有运行数据失败: %w",
	"failed to start FrankenPHP: %w":                                                             "无法启动 FrankenPHP: %w",
	"failed to start Mercure: %w":                                                                "无法启动 Mercure: %w",
	"failed to start scheduler: %w":                                                              "无法启动定时任务: %w",
	"failed to write restored data: %w":                                                          "写入恢复数据失败: %w",
	"follow new log entries":                                                                     "持续输出新增日志",
	"http_address and https_address must differ in managed TLS mode":                             "managed TLS 模式的 http_address 与 https_address 不能相同",
	"install does not accept positional arguments: %s\n":                                         "install 不接受位置参数: %s\n",
	"install must be run as root":                                                                "install 必须使用 root 权限运行",
	"installing HelmDesk service":                                                                "正在安装 HelmDesk 服务",
	"internationalized domain names must use ASCII Punycode in managed TLS mode":                 "managed TLS 模式的国际化域名必须使用 ASCII Punycode",
	"invalid SQLite snapshot: %s":                                                                "SQLite 快照无效: %s",
	"invalid backup compression format: %w":                                                      "备份压缩格式无效: %w",
	"invalid backup manifest: %w":                                                                "备份清单无效: %w",
	"invalid listen address: %w":                                                                 "监听地址无效: %w",
	"invalid public_url: %w":                                                                     "public_url 无效: %w",
	"invalid runtime configuration: %w":                                                          "运行配置无效: %w",
	"logs --lines must be greater than 0":                                                        "logs 的 --lines 必须大于 0",
	"logs does not accept positional arguments: %s\n":                                            "logs 不接受位置参数: %s\n",
	"managed HTTPS listen address":                                                               "托管 HTTPS 监听地址",
	"managed TLS mode does not allow a port in public_url":                                       "managed TLS 模式不支持 public_url 指定端口",
	"managed TLS mode requires a public domain name in public_url":                               "managed TLS 模式要求 public_url 使用公网域名",
	"managed TLS mode requires an https public_url":                                              "managed TLS 模式要求 public_url 使用 https",
	"managed TLS mode requires https_address":                                                    "managed TLS 模式必须配置 https_address",
	"managed TLS runtime configuration is missing https_address":                                 "managed TLS 运行配置缺少 https_address",
	"number of recent log lines to show":                                                         "显示最近日志行数",
	"plain HTTP is listening on a non-loopback address":                                          "明文 HTTP 正在监听非回环地址",
	"plain TLS mode requires an http public_url":                                                 "plain TLS 模式要求 public_url 使用 http",
	"pre-restore safety backup created":                                                          "恢复前安全快照已创建",
	"pre-restore safety backup: %s\n":                                                            "恢复前安全快照: %s\n",
	"preparing Laravel application":                                                              "正在准备 Laravel 应用",
	"public_url cannot be empty":                                                                 "public_url 不能为空",
	"public_url cannot contain a path":                                                           "public_url 不能包含路径",
	"public_url cannot contain user information, query parameters, or a fragment":                "public_url 不能包含用户信息、查询参数或片段",
	"public_url must contain a hostname":                                                         "public_url 必须包含主机名",
	"public_url must contain a valid hostname":                                                   "public_url 必须包含有效主机名",
	"public_url only supports http or https":                                                     "public_url 只支持 http 或 https",
	"queue task failed":                                                                          "队列任务执行失败",
	"queue worker returned an invalid result":                                                    "队列 worker 返回无效结果",
	"replacing business data":                                                                    "正在替换业务数据",
	"restore initialization command exited with code %d":                                         "恢复初始化命令退出码为 %d",
	"restore initialization implementation cannot be empty":                                      "恢复初始化实现不能为空",
	"restore replaces the target business data; add --yes to confirm":                            "恢复会替换目标业务数据，请添加 --yes 确认",
	"restore requires the HelmDesk service to be completely stopped":                             "恢复要求 HelmDesk 服务完全停止",
	"return value has type %T":                                                                   "返回类型为 %T",
	"runtime configuration does not exist: %s":                                                   "运行配置不存在: %s",
	"runtime configuration file":                                                                 "运行配置文件",
	"runtime configuration generated":                                                            "已生成运行配置",
	"runtime configuration is missing http_address":                                              "运行配置缺少 http_address",
	"runtime configuration is missing public_url":                                                "运行配置缺少 public_url",
	"runtime configuration is missing storage_path":                                              "运行配置缺少 storage_path",
	"runtime configuration is missing tls_mode":                                                  "运行配置缺少 tls_mode",
	"runtime directory is in use":                                                                "运行目录正在使用",
	"scheduled task failed":                                                                      "定时任务执行失败",
	"scheduled task is still running; skipped this invocation":                                   "定时任务仍在执行，本轮调度已跳过",
	"scheduler event":                                                                            "定时调度器事件",
	"scheduler started":                                                                          "定时调度器已启动",
	"scheduler error":                                                                            "定时调度器异常",
	"serve does not accept positional arguments: %s\n":                                           "serve 不接受位置参数: %s\n",
	"show logs since the specified time":                                                         "显示指定时间之后的日志",
	"sqlite-vec is not supported on this platform: %s":                                           "当前平台不支持 sqlite-vec: %s",
	"sqlite-vec validation failed: %s":                                                           "sqlite-vec 校验失败: %s",
	"staging directory contains an unsupported file type: %s":                                    "暂存区包含不支持的文件类型: %s",
	"starting HelmDesk":                                                                          "正在启动 HelmDesk",
	"status does not accept arguments":                                                           "status 不接受参数",
	"storage-path cannot be a filesystem root":                                                   "storage-path 不能是根目录",
	"storage-path cannot be empty":                                                               "storage-path 不能为空",
	"storage-path must be an absolute path":                                                      "storage-path 必须是绝对路径",
	"storage_path cannot be a filesystem root":                                                   "storage_path 不能是根目录",
	"storage_path cannot be empty":                                                               "storage_path 不能为空",
	"systemd is not running on this Linux system":                                                "当前 Linux 未运行 systemd",
	"target runtime directory contains incomplete business data; refusing to overwrite it":       "目标运行目录的业务数据不完整，拒绝覆盖",
	"target runtime directory has no business data; skipping pre-restore safety backup":          "目标运行目录没有业务数据，跳过恢复前安全快照",
	"the ProgramData environment variable cannot be empty":                                       "ProgramData 环境变量不能为空",
	"the first backup entry must be a valid manifest.json":                                       "备份首个条目必须是有效 manifest.json",
	"the processed field is missing or has an invalid type":                                      "processed 字段缺失或类型无效",
	"timed out waiting for web service to stop":                                                  "等待 Web 服务退出超时",
	"tls_mode cannot be empty":                                                                   "tls_mode 不能为空",
	"trusted proxy IP/CIDR values for external mode, comma-separated":                            "external 模式的可信代理 IP/CIDR，逗号分隔",
	"trusted_proxies cannot contain other addresses when * is used":                              "trusted_proxies 使用 * 时不能同时配置其它地址",
	"trusted_proxies cannot contain empty values":                                                "trusted_proxies 不能包含空值",
	"trusted_proxies contains an invalid IP address or CIDR: %s":                                 "trusted_proxies 包含无效 IP 或 CIDR: %s",
	"trusted_proxies is only valid in external TLS mode":                                         "trusted_proxies 仅用于 external TLS 模式",
	"unable to process request":                                                                  "无法处理请求",
	"unable to read static file":                                                                 "无法读取静态文件",
	"uninstall must be run as root":                                                              "uninstall 必须使用 root 权限运行",
	"uninstalling HelmDesk service":                                                              "正在卸载 HelmDesk 服务",
	"unknown command: %s\n\n":                                                                    "未知命令: %s\n\n",
	"unsupported TLS mode: %s":                                                                   "不支持的 TLS 模式: %s",
	"unsupported backup format version: %d":                                                      "不支持的备份格式版本: %d",
	"unsupported service operation: %s":                                                          "不支持的服务操作: %s",
	"unsupported tls_mode: %s":                                                                   "不支持的 tls_mode: %s",
	"usage: helmdesk artisan <command> [args...]":                                                "用法: helmdesk artisan <command> [args...]",
	"usage: helmdesk restore --yes [--config file] [--storage-path directory] <backup-file>":     "用法: helmdesk restore --yes [--config 文件] [--storage-path 目录] <备份文件>",
	"validating backup archive":                                                                  "正在校验备份归档",
	"web service is ready":                                                                       "Web 服务已就绪",
	"web service is stopping":                                                                    "Web 服务正在停止",
	"writing backup archive":                                                                     "正在写入备份归档",
	"automatic recovery failed: %w":                                                              "自动恢复失败: %w",
	"automatic upgrade only supports HelmDesk installed by HelmDesk-Setup.exe":                   "自动升级仅支持通过 HelmDesk-Setup.exe 安装的 HelmDesk",
	"automatic upgrade requires a systemd installation":                                          "自动升级要求 HelmDesk 已安装为 systemd 服务",
	"another HelmDesk upgrade is already running":                                                "另一个 HelmDesk 升级正在执行",
	"Built:  %s\n": "构建时间: %s\n",
	"candidate platform is %s/%s, expected %s/%s":                                  "候选程序平台为 %s/%s，期望 %s/%s",
	"candidate version is %s, expected %s":                                         "候选程序版本为 %s，期望 %s",
	"check for updates without installing":                                         "仅检查更新，不执行安装",
	"Commit: %s\n":                                                                 "提交: %s\n",
	"creating pre-upgrade backup":                                                  "正在创建升级前备份",
	"Current version: %s\n":                                                        "当前版本: %s\n",
	"Download size:   %d bytes\n":                                                  "下载大小:   %d 字节\n",
	"Downloading and verifying the update...":                                      "正在下载并校验更新...",
	"failed to close incomplete update file":                                       "关闭未完成的升级文件失败",
	"failed to close health check response":                                        "关闭健康检查响应失败",
	"failed to decode candidate version: %w":                                       "解析候选程序版本失败: %w",
	"failed to read candidate version: %w":                                         "读取候选程序版本失败: %w",
	"failed to release upgrade lock":                                               "释放升级锁失败",
	"failed to remove downloaded update":                                           "删除已下载的升级文件失败",
	"failed to remove incomplete update file":                                      "删除未完成的升级文件失败",
	"HelmDesk is already up to date.":                                              "HelmDesk 已是最新版本。",
	"HelmDesk upgrade completed":                                                   "HelmDesk 升级完成",
	"HelmDesk upgrade failed; starting automatic recovery":                         "HelmDesk 升级失败，正在自动恢复",
	"HelmDesk was upgraded to %s.\n":                                               "HelmDesk 已升级到 %s。\n",
	"HelmDesk will create a backup before installing the update. Continue? [y/N] ": "HelmDesk 将在安装更新前创建备份，是否继续？[y/N] ",
	"installing HelmDesk update":                                                   "正在安装 HelmDesk 更新",
	"Latest version:  %s\n":                                                        "最新版本:   %s\n",
	"launching HelmDesk upgrade installer":                                         "正在启动 HelmDesk 升级安装器",
	"Opening the HelmDesk installer...":                                            "正在打开 HelmDesk 安装器...",
	"output version information as JSON":                                           "以 JSON 输出版本信息",
	"Platform:        %s/%s\n":                                                     "运行平台:   %s/%s\n",
	"pre-upgrade backup created":                                                   "升级前备份已创建",
	"Pre-upgrade backup: %s\n":                                                     "升级前备份: %s\n",
	"restoring pre-upgrade business data":                                          "正在恢复升级前业务数据",
	"restoring previous HelmDesk binary":                                           "正在恢复升级前的 HelmDesk 程序",
	"saving current HelmDesk binary":                                               "正在保存当前 HelmDesk 程序",
	"starting HelmDesk upgrade":                                                    "正在开始 HelmDesk 升级",
	"starting restored HelmDesk service":                                           "正在启动已恢复的 HelmDesk 服务",
	"starting upgraded HelmDesk service":                                           "正在启动升级后的 HelmDesk 服务",
	"stop HelmDesk before running upgrade":                                         "执行升级前请先停止 HelmDesk",
	"stopping HelmDesk service for upgrade":                                        "正在停止 HelmDesk 服务以执行升级",
	"the ProgramFiles environment variable cannot be empty":                        "ProgramFiles 环境变量不能为空",
	"timed out waiting for HelmDesk to become ready on %s":                         "等待 HelmDesk 在 %s 就绪超时",
	"Update download and SHA-256 verification completed.":                          "更新下载及 SHA-256 校验完成。",
	"Upgrade cancelled.":                                                           "升级已取消。",
	"upgrade does not accept positional arguments: %s":                             "upgrade 不接受位置参数: %s",
	"upgrade failed and the previous version was restored: %w":                     "升级失败，已恢复上一版本: %w",
	"upgrade failed: %w":                                                           "升级失败: %w",
	"upgrade must be run as root":                                                  "upgrade 必须使用 root 权限运行",
	"upgrade preparation failed; restarting the current version":                   "升级准备失败，正在重新启动当前版本",
	"version does not accept positional arguments: %s\n":                           "version 不接受位置参数: %s\n",
	"waiting for upgraded HelmDesk to become ready":                                "正在等待升级后的 HelmDesk 就绪",
}
