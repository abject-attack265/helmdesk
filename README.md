# HelmDesk

English | [简体中文](README.zh-CN.md)

HelmDesk is an open-source, self-hosted customer support system for small and medium-sized teams. It brings conversations from websites, Telegram, WeChat Official Accounts, and other channels into one workspace, with AI-powered support, knowledge bases, contact management, and team collaboration.

## Screenshot Preview

### Unified Inbox

[![A customer conversation with order and shipping context in the unified inbox](.github/assets/readme/en/screenshots/inbox.png)](.github/assets/readme/en/screenshots/inbox.png)

### Dashboard

[![Recent conversations, ratings, AI usage, and agent activity](.github/assets/readme/en/screenshots/dashboard.png)](.github/assets/readme/en/screenshots/dashboard.png)

### Contact Management

[![A contact list with a detailed customer profile](.github/assets/readme/en/screenshots/contacts.png)](.github/assets/readme/en/screenshots/contacts.png)

### Knowledge Bases

[![Product and service knowledge groups with a populated document list](.github/assets/readme/en/screenshots/knowledge-base.png)](.github/assets/readme/en/screenshots/knowledge-base.png)

### Automatic Message Translation

With a translation provider configured, HelmDesk can translate incoming and outgoing messages into each teammate's preferred language. The inbox keeps the original text and translation together, so agents can hide the translation or request a fresh one without losing context.

[![Original customer and agent messages displayed with automatic translations](.github/assets/readme/en/screenshots/message-translation.png)](.github/assets/readme/en/screenshots/message-translation.png)

### Translate Before Sending

Agents can write a reply in the language they know best, preview the translation the visitor will receive, and confirm it before sending.

[![An agent previews the visitor-facing translation before sending a reply](.github/assets/readme/en/screenshots/reply-translation-preview.png)](.github/assets/readme/en/screenshots/reply-translation-preview.png)

## Key Features

- Multi-channel conversation intake and a unified inbox
- AI-powered automated support, reply assistance, and conversation summaries
- Automatic inbox translation and visitor-facing reply previews
- Knowledge base management and retrieval testing
- Contacts, tags, custom attributes, and quick replies
- Team members, roles, and permission management
- AI providers, models, object storage, and integration settings

## Conversation Lifecycle

The diagram below shows initial routing, AI and human handoffs, timeout-driven transitions, closing, and reopening.

[![HelmDesk conversation lifecycle state machine](.github/assets/readme/en/diagrams/conversation-state-machine.png)](.github/assets/readme/en/diagrams/conversation-state-machine.svg)

## Technology Stack

- PHP, Laravel 13, Laravel Actions, Laravel Data
- Vue 3, Inertia.js 3, TypeScript, Tailwind CSS 4
- Go, FrankenPHP, Mercure
- SQLite, TNTSearch

## Local Development

PHP 8.5 ZTS, Composer, Node.js, Go, and `php-config` are required.

```bash
git clone git@github.com:helmdesk-ai/helmdesk.git
cd helmdesk
composer setup
make
```

Once started, open `http://localhost:8080`.

## Building

All build artifacts are stored in `build/output`.

### Linux

Linux binaries are built with Docker Buildx, which requires Docker to be installed and running.

The version is required and must be a SemVer value without a `v` prefix:

| Command                            | Description                                                   | Output                                           |
| ---------------------------------- | ------------------------------------------------------------- | ------------------------------------------------ |
| `APP_VERSION=1.0.0 make build`     | Build the Linux binary for the current machine's architecture | `helmdesk-linux-amd64` or `helmdesk-linux-arm64` |
| `APP_VERSION=1.0.0 make build-all` | Build both the Linux AMD64 and ARM64 binaries                 | Binaries for both architectures                  |

### Windows

The Windows distribution must be built on Windows x86_64 with PowerShell 7. Go, Node.js, Git, Visual Studio C++, LLVM, and Inno Setup 6 are also required.

```powershell
.\build\windows.ps1 -AppVersion 1.0.0
```

You can also run `make build-windows APP_VERSION=1.0.0`. By default, the build produces the `HelmDesk-Setup.exe` installer and the `helmdesk-windows-amd64.zip` portable package.

`-AppVersion` is required and must be a SemVer value without a `v` prefix.

Build only the portable package without invoking Inno Setup:

```powershell
.\build\windows.ps1 -AppVersion 1.0.0 -SkipInstaller
```

## Standalone Operation

HelmDesk's Go runtime embeds FrankenPHP, Mercure, and the complete Laravel application. It handles HTTP/HTTPS, static assets, queue processing, and scheduled tasks.

### Linux

We recommend validating the runtime in the foreground before installing it as a systemd service. The examples below use the AMD64 build; on an ARM64 server, replace the filename with `helmdesk-linux-arm64`.

#### Foreground Validation

Download the binary, make it executable, and start the HTTP server:

```bash
chmod +x helmdesk-linux-amd64
./helmdesk-linux-amd64 serve \
  --public-url http://localhost:8080 \
  --tls-mode plain \
  --http-address 0.0.0.0:8080 \
  --storage-path ./storage
```

Open `http://localhost:8080` to verify the installation, then press `Ctrl+C` to stop it.

#### Install as a systemd Service

After validation, run the installation command. The following configuration lets HelmDesk obtain and renew HTTPS certificates automatically. The domain must already resolve to the server, and TCP ports 80 and 443 must be open:

```bash
sudo ./helmdesk-linux-amd64 install \
  --public-url https://support.example.com \
  --tls-mode managed \
  --acme-email admin@example.com \
  --storage-path /var/lib/helmdesk
```

If Caddy, Nginx, or a load balancer already terminates TLS, use external TLS mode:

```bash
sudo ./helmdesk-linux-amd64 install \
  --public-url https://support.example.com \
  --tls-mode external \
  --http-address localhost:8080 \
  --trusted-proxies 127.0.0.1 \
  --storage-path /var/lib/helmdesk
```

The installation command starts the service and copies the program to `/usr/local/bin/helmdesk`. Configuration is stored in `/etc/helmdesk/config.json`, and business data is stored in `/var/lib/helmdesk`.

#### Service Management

| Command                                          | Description                                                                  |
| ------------------------------------------------ | ---------------------------------------------------------------------------- |
| `sudo helmdesk status`                           | Show the service status                                                      |
| `helmdesk version`                               | Show the current version and build metadata                                  |
| `helmdesk upgrade --check`                       | Check the latest stable release                                              |
| `sudo helmdesk upgrade`                          | Back up data and upgrade to the latest stable release                        |
| `sudo helmdesk start`                            | Start the service                                                            |
| `sudo helmdesk stop`                             | Stop the service                                                             |
| `sudo helmdesk restart`                          | Restart the service                                                          |
| `sudo helmdesk logs`                             | Show recent service logs                                                     |
| `sudo helmdesk logs --follow`                    | Follow the service logs                                                      |
| `sudo -u helmdesk helmdesk artisan <command>`    | Run a Laravel Artisan command as the service account                         |
| `sudo -u helmdesk helmdesk backup`               | Create an online backup as the service account                               |
| `sudo -u helmdesk helmdesk restore --yes <file>` | Restore a backup as the service account after stopping the service           |
| `sudo helmdesk uninstall`                        | Remove the service, program, and configuration while retaining business data |
| `helmdesk --help`                                | Show all commands and options                                                |

#### Viewing Logs

The HelmDesk service logs are stored in the systemd journal. They cover service startup and shutdown, port listeners, HTTPS certificates, the FrankenPHP worker, the queue worker, the scheduler, and other runtime status. The `logs` command shows the most recent 200 lines by default. You can also follow the logs or specify a starting time:

```bash
sudo helmdesk logs
sudo helmdesk logs --follow
sudo helmdesk logs --lines 500 --since "1 hour ago"
```

Laravel application logs are stored separately in `<storage-path>/logs`. They include application exceptions, business workflows, queue jobs, third-party calls, and related records. With the default installation path, inspect them with:

```bash
sudo ls -lah /var/lib/helmdesk/logs
sudo tail -n 200 -F /var/lib/helmdesk/logs/laravel-$(date -u +%F).log
```

When installing with a custom `--storage-path`, replace `/var/lib/helmdesk` with the actual directory. Laravel application logs are split by UTC date and retained for 30 days. Service logs and Laravel application logs are independent; `helmdesk logs` does not read or combine Laravel application logs. HelmDesk does not currently record HTTP access logs.

### Windows

`HelmDesk-Setup.exe` installs the program to `C:\Program Files\HelmDesk` and stores configuration and business data in `C:\ProgramData\HelmDesk`. Run the following commands in the “HelmDesk Console” created by the installer.

| Command                         | Description                                                      |
| ------------------------------- | ---------------------------------------------------------------- |
| `helmdesk serve`                | Run the application in the foreground; press `Ctrl+C` to stop it |
| `helmdesk status`               | Check the application status at the default local address        |
| `helmdesk version`              | Show the current version and build metadata                      |
| `helmdesk upgrade --check`      | Check the latest stable release                                  |
| `helmdesk upgrade`              | Download and open the latest stable installer                    |
| `helmdesk artisan <command>`    | Run a Laravel Artisan command                                    |
| `helmdesk backup`               | Create an online backup                                          |
| `helmdesk restore --yes <file>` | Restore a backup after stopping the application                  |
| `helmdesk --help`               | Show all commands and options                                    |

For local use, run the application directly. The default address is `http://localhost:8080`:

```powershell
helmdesk serve
```

For a public domain, the runtime can manage HTTPS. The domain must already resolve to the server, and TCP ports 80 and 443 must be open in the firewall:

```powershell
helmdesk serve `
  --public-url https://support.example.com `
  --tls-mode managed `
  --http-address 0.0.0.0:80 `
  --https-address 0.0.0.0:443 `
  --acme-email admin@example.com
```

The installer does not modify the firewall. Before starting HelmDesk, confirm that IIS or another service is not using the required ports.

Before upgrading, press `Ctrl+C` in the window running `helmdesk serve`, then run `helmdesk upgrade`. The command verifies the GitHub Release asset SHA-256, creates a pre-upgrade backup, and opens the graphical installer. The Windows installer is currently unsigned, so Windows may show an “unknown publisher” or SmartScreen warning. Upgrade integrity is still checked by the installed HelmDesk against the immutable GitHub Release and its asset digest.

Automatic upgrade is not available for the portable ZIP package because PHP and other runtime libraries may also change. Stop HelmDesk and replace the complete runtime directory instead.

## Releasing

Pushing a `v<major>.<minor>.<patch>` tag passes the tag version to every build job, builds Linux AMD64, Linux ARM64, the Windows installer, the Windows portable package, and a multi-platform container image, then publishes the image to GitHub Container Registry and creates a GitHub Release. Immutable releases must be enabled under the repository's Releases settings; automatic upgrade rejects mutable releases.

Windows releases may remain unsigned for now. After obtaining an Authenticode certificate, pass `-SigningCertificateThumbprint` and `-RequireSigning` to the release build.

## Docker

Release images are published to GitHub Container Registry with full, major/minor, and `latest` tags. Versions starting from `1.0.0` also receive a major-version tag:

```bash
docker pull ghcr.io/helmdesk-ai/helmdesk:latest
```

After the first publication, set the `helmdesk` package visibility to Public in the GitHub organization package settings so it can be pulled without authentication.

Managed HTTPS:

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

Plain HTTP:

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

The image always listens on ports 8080 and 8443 and runs as a non-root user. `/data` persists business data and certificates. The following environment variables are available:

- `HELMDESK_PUBLIC_URL`
- `HELMDESK_TLS_MODE`
- `HELMDESK_HTTP_ADDRESS`
- `HELMDESK_HTTPS_ADDRESS`
- `HELMDESK_ACME_EMAIL`
- `HELMDESK_TRUSTED_PROXIES`
- `HELMDESK_STORAGE_PATH`

## Backup and Restore

Create an online backup:

```bash
helmdesk backup
helmdesk backup --output /srv/backup
helmdesk backup --storage-path /srv/helmdesk --output /srv/backup
helmdesk backup --config /etc/helmdesk/config.json
```

By default, the backup is written to `<storage_path>/backups/helmdesk-backup-<UTC timestamp>.tar.gz`. It includes the primary business database, the knowledge base database, the runtime directory's `.env` file, and local business files. It excludes caches, sessions, queues, temporary files, logs, certificates, and attachment objects stored in S3.

Backups contain runtime keys and business data. Store them in encrypted, access-controlled storage.

HelmDesk must be completely stopped before a restore:

```bash
sudo helmdesk stop
sudo -u helmdesk helmdesk restore --yes /srv/backup/helmdesk-backup-20260727T120000Z.tar.gz
sudo helmdesk start
```

Windows:

```powershell
helmdesk restore --yes D:\Backup\helmdesk-backup-20260727T120000Z.tar.gz
```

Docker:

```bash
# Create an online backup and copy it to the host
docker exec helmdesk helmdesk backup
docker cp helmdesk:/data/backups/helmdesk-backup-20260727T120000Z.tar.gz .

# Restore the data volume offline
docker stop helmdesk
docker run --rm \
  -v helmdesk-data:/data \
  -v "$PWD:/backup:ro" \
  helmdesk:latest restore --yes /backup/helmdesk-backup-20260727T120000Z.tar.gz
docker start helmdesk
```

The restore process verifies the backup format, file scope, size, and SHA-256 digests, and creates a pre-restore snapshot in `<storage_path>/backups`. After the restore completes, HelmDesk initializes the cache, session, and queue databases and rebuilds the TNTSearch indexes.

## License

[MIT](LICENSE)
