# syntax=docker/dockerfile:1.7

FROM composer/composer:latest-bin AS composer-bin

FROM dunglas/frankenphp:1.12.6-builder-php8.5-trixie AS app-builder

RUN install-php-extensions pdo_sqlite intl zip bcmath && \
    curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - && \
    apt-get install -y --no-install-recommends nodejs && \
    rm -rf /var/lib/apt/lists/*

COPY --from=composer-bin /composer /usr/bin/composer
WORKDIR /build
COPY . .
# phpstorm-stubs 的测试目录不参与运行时反射。
RUN composer install --ignore-platform-reqs --no-dev --optimize-autoloader --no-interaction && \
    npm ci && \
    npm run build && \
    echo "Removing build-only Composer files." && \
    rm -rf vendor/jetbrains/phpstorm-stubs/tests

FROM dunglas/frankenphp:static-builder-gnu-1.12.6 AS final

ARG APP_VERSION
ARG APP_COMMIT=unknown
ARG APP_BUILD_DATE=unknown

WORKDIR /work
RUN test -n "${APP_VERSION}" || \
    (echo "ERROR: APP_VERSION build argument is required." >&2; exit 1)
COPY --from=app-builder /build/app ./php-app/app
COPY --from=app-builder /build/artisan ./php-app/artisan
COPY --from=app-builder /build/bootstrap ./php-app/bootstrap
COPY --from=app-builder /build/config ./php-app/config
COPY --from=app-builder /build/database ./php-app/database
COPY --from=app-builder /build/lang ./php-app/lang
COPY --from=app-builder /build/public ./php-app/public
COPY --from=app-builder /build/resources ./php-app/resources
COPY --from=app-builder /build/routes ./php-app/routes
COPY --from=app-builder /build/vendor ./php-app/vendor
COPY --from=app-builder /build/composer.json /build/composer.lock ./php-app/
COPY go.mod go.sum ./
COPY cmd ./cmd
COPY internal ./internal

RUN cd /work/php-app && \
    if find . -type d -name node_modules -print -quit | grep -q .; then \
        echo "ERROR: node_modules must not be embedded in the application archive." >&2; \
        exit 1; \
    fi && \
    echo "Embedded application contents:" && \
    du -sh ./* | sort -h && \
    tar -cf /go/src/app/app.tar . && \
    echo "Application archive:" && \
    du -h /go/src/app/app.tar && \
    sha256sum /go/src/app/app.tar | awk '{printf "%s", $1}' > /go/src/app/app_checksum.txt && \
    cd /work && \
    echo "replace github.com/dunglas/frankenphp => /go/src/app" >> go.mod

ENV PHP_ROOT=/go/src/app/dist/static-php-cli/buildroot
ENV CGO_ENABLED=1
RUN mkdir -p dist && \
    export PATH="${PHP_ROOT}/bin:${PATH}" && \
    export CGO_CFLAGS="$(php-config --includes) -I${PHP_ROOT}/include" && \
    export CGO_LDFLAGS="$(php-config --ldflags) $(php-config --libs) -L${PHP_ROOT}/lib -lpq -lpgcommon -lpgport -lhashkit -lssl -lcrypto" && \
    go build -mod=mod -tags "netgo,osusergo,nowatcher,nobadger,nomysql,nopgx" \
        -ldflags="-s -w -X helmdesk/internal/buildinfo.Version=${APP_VERSION} -X helmdesk/internal/buildinfo.Commit=${APP_COMMIT} -X helmdesk/internal/buildinfo.BuildDate=${APP_BUILD_DATE}" \
        -o "dist/helmdesk-linux-$(uname -m)" ./cmd/helmdesk
