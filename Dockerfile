# syntax=docker/dockerfile:1.7

FROM debian:trixie-slim

ARG TARGETARCH
ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && \
    apt-get install -y --no-install-recommends ca-certificates && \
    rm -rf /var/lib/apt/lists/* && \
    groupadd --gid 10001 helmdesk && \
    useradd --uid 10001 --gid helmdesk --home-dir /data --shell /usr/sbin/nologin helmdesk && \
    mkdir /data && \
    chown helmdesk:helmdesk /data

COPY --chown=helmdesk:helmdesk build/output/helmdesk-linux-${TARGETARCH} /usr/local/bin/helmdesk

WORKDIR /data
VOLUME ["/data"]
EXPOSE 8080 8443
USER helmdesk

ENV HELMDESK_HTTP_ADDRESS=0.0.0.0:8080
ENV HELMDESK_HTTPS_ADDRESS=0.0.0.0:8443
ENV HELMDESK_STORAGE_PATH=/data

ENTRYPOINT ["/usr/local/bin/helmdesk"]
CMD ["serve"]
