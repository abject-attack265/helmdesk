#!/usr/bin/env bash

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT="${ROOT}/build/output"
PLATFORM="amd64"
: "${APP_VERSION:?必须通过 APP_VERSION 传入版本号，例如 APP_VERSION=1.2.3}"
if [[ -z "${APP_COMMIT:-}" ]]; then
    APP_COMMIT="$(git -C "${ROOT}" rev-parse HEAD)"
fi
APP_BUILD_DATE="${APP_BUILD_DATE:-$(date -u +%Y-%m-%dT%H:%M:%SZ)}"

if [[ ! "${APP_VERSION}" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)(-[0-9A-Za-z-]+(\.[0-9A-Za-z-]+)*)?(\+[0-9A-Za-z-]+(\.[0-9A-Za-z-]+)*)?$ ]]; then
    echo "APP_VERSION 必须是 SemVer 版本号且不带 v 前缀: ${APP_VERSION}"
    exit 1
fi
version_precedence="${APP_VERSION%%+*}"
if [[ "${version_precedence}" == *-* ]]; then
    prerelease="${version_precedence#*-}"
    IFS="." read -r -a identifiers <<< "${prerelease}"
    for identifier in "${identifiers[@]}"; do
        if [[ "${identifier}" =~ ^0[0-9]+$ ]]; then
            echo "APP_VERSION 的数字预发布标识不能包含前导零: ${APP_VERSION}"
            exit 1
        fi
    done
fi
if [[ ! "${APP_COMMIT}" =~ ^[0-9a-fA-F]{40}$ ]]; then
    echo "APP_COMMIT 必须是完整的 Git 提交哈希: ${APP_COMMIT}"
    exit 1
fi

while getopts "p:" option; do
    case "${option}" in
        p) PLATFORM="${OPTARG}" ;;
        *) echo "用法: $0 [-p amd64|arm64|all]"; exit 1 ;;
    esac
done

if [[ "${PLATFORM}" != "amd64" && "${PLATFORM}" != "arm64" && "${PLATFORM}" != "all" ]]; then
    echo "不支持的平台: ${PLATFORM}"
    exit 1
fi

mkdir -p "${OUTPUT}"
echo "构建 HelmDesk ${APP_VERSION} (${APP_COMMIT})，目标平台: ${PLATFORM}"

build_platform() {
    local architecture="$1"
    local machine="$2"
    local image="helmdesk-binary:${architecture}"

    docker buildx build \
        --platform "linux/${architecture}" \
        --build-arg "APP_VERSION=${APP_VERSION}" \
        --build-arg "APP_COMMIT=${APP_COMMIT}" \
        --build-arg "APP_BUILD_DATE=${APP_BUILD_DATE}" \
        --load \
        -f "${ROOT}/build/Dockerfile" \
        -t "${image}" \
        "${ROOT}"
    local container
    container="$(docker create "${image}")"
    docker cp "${container}:/work/dist/helmdesk-linux-${machine}" "${OUTPUT}/helmdesk-linux-${architecture}"
    docker rm "${container}" >/dev/null
}

if [[ "${PLATFORM}" == "amd64" || "${PLATFORM}" == "all" ]]; then
    build_platform amd64 x86_64
fi
if [[ "${PLATFORM}" == "arm64" || "${PLATFORM}" == "all" ]]; then
    build_platform arm64 aarch64
fi

ls -lh "${OUTPUT}"/helmdesk-linux-*
