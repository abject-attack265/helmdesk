# 本地 Go 运行时编译参数
UNAME_S := $(shell uname -s)
PHP_CONFIG ?= php-config
# PHP 相关变量惰性展开，构建镜像等 target 无需本地安装 PHP
PHP_ROOT = $(shell $(PHP_CONFIG) --prefix)
PHP_BIN ?= $(PHP_ROOT)/bin/php
# macOS 走 Homebrew，Linux 约定安装到 /usr/local；未安装时自动退化为 nowatcher
ifndef WATCHER_ROOT
WATCHER_ROOT := $(shell (command -v brew >/dev/null 2>&1 && brew --prefix watcher 2>/dev/null) || (test -f /usr/local/include/wtr/watcher-c.h && echo /usr/local) || true)
endif
GO_WATCHER_TAG = $(if $(WATCHER_ROOT),,-tags nowatcher)
CGO_CFLAGS = $(shell $(PHP_CONFIG) --includes) -I$(PHP_ROOT)/include $(if $(WATCHER_ROOT),-I$(WATCHER_ROOT)/include)
CGO_LDFLAGS = -L$(PHP_ROOT)/lib $(shell $(PHP_CONFIG) --ldflags) $(shell $(PHP_CONFIG) --libs) $(if $(WATCHER_ROOT),-L$(WATCHER_ROOT)/lib -lwatcher-c)
# Linux 的动态链接器不记录 -L 路径，需要写入 rpath 才能找到自建前缀下的 libphp.so
ifeq ($(UNAME_S),Linux)
CGO_LDFLAGS += -Wl,-rpath,$(PHP_ROOT)/lib
ifneq ($(WATCHER_ROOT),)
CGO_LDFLAGS += -Wl,-rpath,$(WATCHER_ROOT)/lib
endif
endif
BUILD_ARCH := $(shell uname -m | sed 's/x86_64/amd64/;s/aarch64/arm64/')
IMAGE ?= helmdesk:latest
REGISTRY_IMAGE := registry.cn-hangzhou.aliyuncs.com/shellphy/helmdesk
TAG ?= latest
TUNNEL_NAME ?= helmdesk
APP_VERSION ?=
export APP_VERSION

.DEFAULT_GOAL := dev

# 校验本地 PHP 是否满足嵌入式运行时的要求
.PHONY: check-php
check-php:
	@command -v $(PHP_CONFIG) >/dev/null 2>&1 || { echo "找不到 $(PHP_CONFIG)，请安装 PHP 8.5 ZTS 并将其 bin 目录加入 PATH"; exit 1; }
	@$(PHP_BIN) -r 'exit(PHP_ZTS ? 0 : 1);' || { echo "$(PHP_BIN) 不是 ZTS 构建，FrankenPHP 只能启动 1 个线程，worker 无法运行"; exit 1; }
	@ls $(PHP_ROOT)/lib/libphp* >/dev/null 2>&1 || { echo "$(PHP_ROOT)/lib 下没有 libphp，请使用 --enable-embed 重新编译 PHP"; exit 1; }
	@$(if $(WATCHER_ROOT),,echo "提示: 未检测到 watcher-c，已启用 nowatcher，dev 模式不会在文件变更时重启 worker")

# 启动 Go、FrankenPHP 和 Vite 开发环境
.PHONY: dev
dev: check-php
	CGO_ENABLED=1 CGO_CFLAGS='$(CGO_CFLAGS)' CGO_LDFLAGS='$(CGO_LDFLAGS)' go build $(GO_WATCHER_TAG) -mod=mod -o storage/framework/cache/helmdesk-dev ./cmd/helmdesk
	npx concurrently --kill-others --kill-timeout 3000 --names server,vite --prefix-colors blue,magenta "storage/framework/cache/helmdesk-dev serve --public-url http://localhost:8080 --tls-mode plain --http-address localhost:8080" "npm run dev"

# 通过命名 Cloudflare Tunnel 暴露本地开发服务
.PHONY: tunnel
tunnel:
	cloudflared tunnel run --url http://localhost:8080 $(TUNNEL_NAME)

# 校验发布产物携带显式版本号
.PHONY: check-build-version
check-build-version:
	@if [ -z "$$APP_VERSION" ]; then echo "必须传入版本号，例如: APP_VERSION=1.2.3 make build"; exit 1; fi

# 构建当前 CPU 架构的 Linux 独立二进制
.PHONY: build
build: check-build-version
	./build/linux.sh -p $(BUILD_ARCH)

# 构建 Linux AMD64 和 ARM64 独立二进制
.PHONY: build-all
build-all: check-build-version
	./build/linux.sh -p all

# 在 Windows x86_64 环境构建独立运行包和安装器
.PHONY: build-windows
build-windows: check-build-version
	pwsh -File ./build/windows.ps1 -AppVersion "$$APP_VERSION"

# 将当前 CPU 架构的独立二进制封装为运行镜像
.PHONY: image
image: build
	docker build --platform linux/$(BUILD_ARCH) --build-arg TARGETARCH=$(BUILD_ARCH) -t $(IMAGE) .

# 构建并推送 Linux AMD64 和 ARM64 镜像
.PHONY: push
push: build-all
	docker buildx build --platform linux/amd64,linux/arm64 --push -t $(REGISTRY_IMAGE):$(TAG) .
