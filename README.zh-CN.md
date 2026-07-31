# HelmDesk

[English](README.md) | 简体中文

HelmDesk 是一套面向中小团队的开源客服系统，可自行部署。它把网站、Telegram、微信公众号等渠道的会话集中到一个工作台，并提供 AI 接待、知识库、联系人管理和团队协作能力。

## 界面预览

### 统一收件箱

[![统一收件箱中的客户会话与订单业务上下文](.github/assets/readme/zh-CN/screenshots/inbox.png)](.github/assets/readme/zh-CN/screenshots/inbox.png)

### 数据概览

[![近期会话、评价、AI 用量和客服接待数据概览](.github/assets/readme/zh-CN/screenshots/dashboard.png)](.github/assets/readme/zh-CN/screenshots/dashboard.png)

### 联系人管理

[![联系人列表与重点客户详情](.github/assets/readme/zh-CN/screenshots/contacts.png)](.github/assets/readme/zh-CN/screenshots/contacts.png)

### 知识库

[![产品与服务知识库的分组和文档列表](.github/assets/readme/zh-CN/screenshots/knowledge-base.png)](.github/assets/readme/zh-CN/screenshots/knowledge-base.png)

### 消息自动翻译

配置翻译供应商后，HelmDesk 可以按照客服的语言偏好自动翻译访客与客服消息。收件箱会同时保留原文和译文，客服可以随时隐藏译文或重新翻译，不会丢失会话上下文。

[![访客与客服消息同时显示原文和自动生成的译文](.github/assets/readme/zh-CN/screenshots/message-translation.png)](.github/assets/readme/zh-CN/screenshots/message-translation.png)

### 发送前自动翻译

客服可以使用自己熟悉的语言编写回复，在发送前预览访客将收到的译文，确认内容后再发送。

[![客服在发送回复前预览访客将看到的译文](.github/assets/readme/zh-CN/screenshots/reply-translation-preview.png)](.github/assets/readme/zh-CN/screenshots/reply-translation-preview.png)

## 主要功能

- 多渠道会话接入与统一收件箱
- AI 自动接待、回复辅助和会话总结
- 收件箱消息自动翻译与发送前译文预览
- 知识库管理与召回测试
- 联系人、标签、自定义属性和快捷回复
- 团队成员、角色与权限管理
- AI 供应商、模型、对象存储和集成配置

## 会话生命周期

下图展示新会话路由、AI 与人工接待切换、超时流转、关单和重开路径。

[![HelmDesk 会话生命周期状态转移图](.github/assets/readme/zh-CN/diagrams/conversation-state-machine.png)](.github/assets/readme/zh-CN/diagrams/conversation-state-machine.svg)

## 技术栈

- PHP、Laravel 13、Laravel Actions、Laravel Data
- Vue 3、Inertia.js 3、TypeScript、Tailwind CSS 4
- Go、FrankenPHP、Mercure
- SQLite、TNTSearch

## 本地开发

需要 PHP 8.5 ZTS、Composer、Node.js、Go 和 `php-config`。

```bash
git clone git@github.com:helmdesk-ai/helmdesk.git
cd helmdesk
composer setup
make
```

启动后访问 `http://localhost:8080`。

## 构建

构建产物统一保存在 `build/output`。

### Linux

Linux 二进制通过 Docker Buildx 构建，需要安装并启动 Docker。

版本号是必传项，使用不带 `v` 前缀的 SemVer：

| 命令                               | 说明                                 | 产物                                             |
| ---------------------------------- | ------------------------------------ | ------------------------------------------------ |
| `APP_VERSION=1.0.0 make build`     | 构建当前电脑架构对应的 Linux 二进制  | `helmdesk-linux-amd64` 或 `helmdesk-linux-arm64` |
| `APP_VERSION=1.0.0 make build-all` | 同时构建 Linux AMD64 和 ARM64 二进制 | 两个架构的二进制                                 |

### Windows

Windows 运行包需要在 Windows x86_64 和 PowerShell 7 环境中构建，并安装 Go、Node.js、Git、Visual Studio C++、LLVM 和 Inno Setup 6。

```powershell
.\build\windows.ps1 -AppVersion 1.0.0
```

也可以执行 `make build-windows APP_VERSION=1.0.0`。默认生成安装器 `HelmDesk-Setup.exe` 和便携包 `helmdesk-windows-amd64.zip`。

`-AppVersion` 是必传项，使用不带 `v` 前缀的 SemVer。

只生成便携包，不调用 Inno Setup：

```powershell
.\build\windows.ps1 -AppVersion 1.0.0 -SkipInstaller
```

## 独立运行

HelmDesk 的 Go 运行时嵌入 FrankenPHP、Mercure 和完整的 Laravel 应用，负责 HTTP/HTTPS、静态资源、队列消费和定时任务。

### Linux

建议先以前台方式验证运行环境，确认访问正常后再安装为 systemd 服务。以下示例使用 AMD64 版本；ARM64 服务器将文件名替换为 `helmdesk-linux-arm64`。

#### 前台验证

下载二进制并添加执行权限，然后启动 HTTP 服务：

```bash
chmod +x helmdesk-linux-amd64
./helmdesk-linux-amd64 serve \
  --public-url http://localhost:8080 \
  --tls-mode plain \
  --http-address 0.0.0.0:8080 \
  --storage-path ./storage
```

访问 `http://localhost:8080` 完成验证，按 `Ctrl+C` 停止。

#### 安装为 systemd 服务

验证通过后执行安装命令。以下配置由 HelmDesk 自动申请和续期 HTTPS 证书，域名需提前解析到服务器，并开放 TCP 80/443：

```bash
sudo ./helmdesk-linux-amd64 install \
  --public-url https://support.example.com \
  --tls-mode managed \
  --acme-email admin@example.com \
  --storage-path /var/lib/helmdesk
```

已有 Caddy、Nginx 或负载均衡时，使用外部 TLS 模式：

```bash
sudo ./helmdesk-linux-amd64 install \
  --public-url https://support.example.com \
  --tls-mode external \
  --http-address localhost:8080 \
  --trusted-proxies 127.0.0.1 \
  --storage-path /var/lib/helmdesk
```

安装命令会启动服务，并将程序复制到 `/usr/local/bin/helmdesk`。配置保存在 `/etc/helmdesk/config.json`，业务数据保存在 `/var/lib/helmdesk`。

#### 服务管理

| 命令                                             | 说明                                |
| ------------------------------------------------ | ----------------------------------- |
| `sudo helmdesk status`                           | 查看服务状态                        |
| `helmdesk version`                               | 查看当前版本和构建信息              |
| `helmdesk upgrade --check`                       | 检查最新正式版本                    |
| `sudo helmdesk upgrade`                          | 备份数据并升级到最新正式版本        |
| `sudo helmdesk start`                            | 启动服务                            |
| `sudo helmdesk stop`                             | 停止服务                            |
| `sudo helmdesk restart`                          | 重启服务                            |
| `sudo helmdesk logs`                             | 查看最近的服务运行日志              |
| `sudo helmdesk logs --follow`                    | 持续查看服务运行日志                |
| `sudo -u helmdesk helmdesk artisan <命令>`       | 以服务账户执行 Laravel Artisan 命令 |
| `sudo -u helmdesk helmdesk backup`               | 以服务账户在线创建备份              |
| `sudo -u helmdesk helmdesk restore --yes <文件>` | 停止服务后以服务账户恢复备份        |
| `sudo helmdesk uninstall`                        | 移除服务、程序和配置，保留业务数据  |
| `helmdesk --help`                                | 查看完整命令和参数                  |

#### 查看日志

HelmDesk 服务运行日志由 systemd journal 保存，包含服务启动与停止、端口监听、HTTPS 证书、FrankenPHP Worker、队列 Worker 和定时调度器等运行状态。`logs` 命令默认显示最近 200 行，也可以持续跟踪或指定起始时间：

```bash
sudo helmdesk logs
sudo helmdesk logs --follow
sudo helmdesk logs --lines 500 --since "1 hour ago"
```

Laravel 应用日志独立保存在 `<storage-path>/logs`，包含应用异常、业务流程、队列任务和第三方调用等记录。默认安装路径下可通过以下命令查看：

```bash
sudo ls -lah /var/lib/helmdesk/logs
sudo tail -n 200 -F /var/lib/helmdesk/logs/laravel-$(date -u +%F).log
```

使用自定义 `--storage-path` 安装时，请将 `/var/lib/helmdesk` 替换为实际目录。Laravel 应用日志按 UTC 日期切分并保留 30 天。服务运行日志与 Laravel 应用日志相互独立，`helmdesk logs` 不读取或混合 Laravel 应用日志。HelmDesk 当前不记录 HTTP access log。

### Windows

`HelmDesk-Setup.exe` 将程序安装到 `C:\Program Files\HelmDesk`，配置和业务数据保存在 `C:\ProgramData\HelmDesk`。以下命令在安装器创建的“HelmDesk 控制台”中执行。

| 命令                            | 说明                           |
| ------------------------------- | ------------------------------ |
| `helmdesk serve`                | 前台启动应用，按 `Ctrl+C` 停止 |
| `helmdesk status`               | 检查默认本地地址上的运行状态   |
| `helmdesk version`              | 查看当前版本和构建信息         |
| `helmdesk upgrade --check`      | 检查最新正式版本               |
| `helmdesk upgrade`              | 下载并打开最新正式版安装器     |
| `helmdesk artisan <命令>`       | 执行 Laravel Artisan 命令      |
| `helmdesk backup`               | 在线创建备份                   |
| `helmdesk restore --yes <文件>` | 停止应用后恢复备份             |
| `helmdesk --help`               | 查看完整命令和参数             |

本机使用时直接运行，默认地址为 `http://localhost:8080`：

```powershell
helmdesk serve
```

使用公网域名时，可以由运行时托管 HTTPS。域名需提前解析到服务器，并在防火墙中开放 TCP 80/443：

```powershell
helmdesk serve `
  --public-url https://support.example.com `
  --tls-mode managed `
  --http-address 0.0.0.0:80 `
  --https-address 0.0.0.0:443 `
  --acme-email admin@example.com
```

安装器不会修改防火墙。启动前需确认端口未被 IIS 等服务占用。

升级前先在运行 `helmdesk serve` 的窗口中按 `Ctrl+C`，然后执行 `helmdesk upgrade`。命令会校验 GitHub Release 资产的 SHA-256、创建升级前备份并打开图形安装器。Windows 安装器当前没有代码签名，系统可能显示“未知发布者”或 SmartScreen 提示；升级完整性由已安装的 HelmDesk 通过 GitHub 不可变 Release 和资产摘要校验。

ZIP 便携包不支持自动升级，因为 PHP 和其它运行库文件也可能随版本变化；请停止 HelmDesk 后替换整个运行目录。

## 发布

推送 `v<major>.<minor>.<patch>` 标签会把标签中的版本号传入所有构建任务，构建 Linux AMD64、Linux ARM64、Windows 安装器、Windows 便携包和多架构容器镜像，将镜像发布到 GitHub Container Registry，然后创建 GitHub Release。仓库必须在 Settings 的 Releases 区域启用 immutable releases，自动升级会拒绝可变 Release。

Windows 发布允许暂时不配置 Authenticode 证书；取得代码签名证书后，可在发布构建中传入 `-SigningCertificateThumbprint` 和 `-RequireSigning`。

## Docker

正式镜像发布到 GitHub Container Registry，并提供完整版本、主次版本和 `latest` 标签；从 `1.0.0` 开始还会提供主版本标签：

```bash
docker pull ghcr.io/helmdesk-ai/helmdesk:latest
```

首次发布后，需要在 GitHub 组织的 Package 设置中将 `helmdesk` 可见性设为 Public，未登录用户才能直接拉取。

托管 HTTPS：

```bash
docker run -d \
  --name helmdesk \
  --restart unless-stopped \
  -p 80:8080 \
  -p 443:8443 \
  -e HELMDESK_PUBLIC_URL=https://support.example.com \
  -e HELMDESK_TLS_MODE=managed \
  -e HELMDESK_ACME_EMAIL=admin@example.com \
  -v helmdesk-data:/data \
  ghcr.io/helmdesk-ai/helmdesk:latest
```

明文 HTTP：

```bash
docker run -d \
  --name helmdesk \
  --restart unless-stopped \
  -p 8080:8080 \
  -e HELMDESK_PUBLIC_URL=http://localhost:8080 \
  -e HELMDESK_TLS_MODE=plain \
  -v helmdesk-data:/data \
  ghcr.io/helmdesk-ai/helmdesk:latest
```

镜像固定监听 8080/8443，并使用非 root 用户运行。`/data` 用于持久化业务数据和证书。可用环境变量：

- `HELMDESK_PUBLIC_URL`
- `HELMDESK_TLS_MODE`
- `HELMDESK_HTTP_ADDRESS`
- `HELMDESK_HTTPS_ADDRESS`
- `HELMDESK_ACME_EMAIL`
- `HELMDESK_TRUSTED_PROXIES`
- `HELMDESK_STORAGE_PATH`

## 备份与恢复

在线备份：

```bash
helmdesk backup
helmdesk backup --output /srv/backup
helmdesk backup --storage-path /srv/helmdesk --output /srv/backup
helmdesk backup --config /etc/helmdesk/config.json
```

默认文件为 `<storage_path>/backups/helmdesk-backup-<UTC 时间>.tar.gz`。备份包含主业务库、知识库、运行目录 `.env` 和本地业务文件，不包含缓存、Session、队列、临时文件、日志、证书及 S3 中的附件对象。

备份中含有运行密钥和业务数据，应保存在受访问控制的加密存储中。

恢复前必须完全停止 HelmDesk：

```bash
sudo helmdesk stop
sudo -u helmdesk helmdesk restore --yes /srv/backup/helmdesk-backup-20260727T120000Z.tar.gz
sudo helmdesk start
```

Windows：

```powershell
helmdesk restore --yes D:\Backup\helmdesk-backup-20260727T120000Z.tar.gz
```

Docker：

```bash
# 在线备份并复制到宿主机
docker exec helmdesk helmdesk backup
docker cp helmdesk:/data/backups/helmdesk-backup-20260727T120000Z.tar.gz .

# 离线恢复数据卷
docker stop helmdesk
docker run --rm \
  -v helmdesk-data:/data \
  -v "$PWD:/backup:ro" \
  helmdesk:latest restore --yes /backup/helmdesk-backup-20260727T120000Z.tar.gz
docker start helmdesk
```

恢复会校验备份格式、文件范围、大小和 SHA-256 摘要，并在 `<storage_path>/backups` 中创建恢复前快照。恢复完成后会初始化缓存、Session 和队列库，并重建 TNTSearch 索引。

## 许可证

[MIT](LICENSE)
