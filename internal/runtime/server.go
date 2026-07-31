package runtime

import (
	"context"
	"crypto/tls"
	"errors"
	"fmt"
	"log/slog"
	"net"
	"net/http"
	"strings"
	"time"

	"helmdesk/internal/localization"
	"helmdesk/internal/runtimeconfig"

	"golang.org/x/crypto/acme/autocert"
)

type httpEndpoint struct {
	name       string
	server     *http.Server
	listener   net.Listener
	managedTLS bool
}

type httpServerResult struct {
	name string
	err  error
}

// serveHTTP 绑定当前 TLS 模式的全部入口并统一处理停止和错误。
func serveHTTP(ctx context.Context, cfg *config, handler http.Handler, stopRuntime context.CancelFunc) error {
	endpoints, err := httpEndpoints(ctx, cfg, handler)
	if err != nil {
		return err
	}
	logHTTPReady(cfg)

	results := make(chan httpServerResult, len(endpoints))
	for _, endpoint := range endpoints {
		go func() {
			results <- httpServerResult{name: endpoint.name, err: endpoint.serve()}
		}()
	}

	remaining := len(endpoints)
	var runErr error
	select {
	case result := <-results:
		remaining--
		if !errors.Is(result.err, http.ErrServerClosed) {
			runErr = fmt.Errorf(localization.Text("%s server exited unexpectedly: %w"), result.name, result.err)
		}
	case <-ctx.Done():
		slog.Info(localization.Text("web service is stopping"))
	}

	stopRuntime()
	shutdownCtx, shutdownCancel := context.WithTimeout(context.Background(), 30*time.Second)
	defer shutdownCancel()
	for _, endpoint := range endpoints {
		if err := endpoint.server.Shutdown(shutdownCtx); err != nil {
			runErr = errors.Join(runErr, fmt.Errorf(localization.Text("%s server failed to stop: %w"), endpoint.name, err))
		}
		if err := endpoint.listener.Close(); err != nil && !errors.Is(err, net.ErrClosed) {
			runErr = errors.Join(runErr, fmt.Errorf(localization.Text("%s listener failed to stop: %w"), endpoint.name, err))
		}
	}
	for remaining > 0 {
		select {
		case result := <-results:
			remaining--
			if !errors.Is(result.err, http.ErrServerClosed) {
				runErr = errors.Join(runErr, fmt.Errorf(localization.Text("%s server exited unexpectedly: %w"), result.name, result.err))
			}
		case <-shutdownCtx.Done():
			return errors.Join(runErr, errors.New(localization.Text("timed out waiting for web service to stop")))
		}
	}

	return runErr
}

// httpEndpoints 在返回前完成全部端口绑定。
func httpEndpoints(ctx context.Context, cfg *config, handler http.Handler) ([]httpEndpoint, error) {
	serviceConfig := cfg.runtimeConfig
	switch serviceConfig.TLSMode {
	case runtimeconfig.TLSModePlain, runtimeconfig.TLSModeExternal:
		endpoint, err := newHTTPEndpoint("HTTP", serviceConfig.HTTPAddress, handler, nil)
		if err != nil {
			return nil, err
		}

		return []httpEndpoint{endpoint}, nil
	case runtimeconfig.TLSModeManaged:
		domain := serviceConfig.Hostname()
		cache := autocert.DirCache(cfg.certificateCachePath)
		logCertificateCacheState(ctx, cache, domain)
		manager := &autocert.Manager{
			Prompt:     autocert.AcceptTOS,
			HostPolicy: autocert.HostWhitelist(domain),
			Cache:      certificateCache{Cache: cache, domain: domain},
			Email:      serviceConfig.ACMEEmail,
		}
		challengeEndpoint, err := newHTTPEndpoint(
			"HTTP/ACME",
			serviceConfig.HTTPAddress,
			manager.HTTPHandler(canonicalHTTPSRedirect(serviceConfig.PublicURL)),
			nil,
		)
		if err != nil {
			return nil, err
		}
		tlsConfig := manager.TLSConfig()
		tlsConfig.MinVersion = tls.VersionTLS12
		httpsEndpoint, err := newHTTPEndpoint("HTTPS", serviceConfig.HTTPSAddress, handler, tlsConfig)
		if err != nil {
			challengeEndpoint.listener.Close()
			return nil, err
		}

		return []httpEndpoint{challengeEndpoint, httpsEndpoint}, nil
	default:
		return nil, fmt.Errorf(localization.Text("unsupported TLS mode: %s"), serviceConfig.TLSMode)
	}
}

// newHTTPEndpoint 创建 HTTP Server 并立即绑定监听地址。
func newHTTPEndpoint(name, address string, handler http.Handler, tlsConfig *tls.Config) (httpEndpoint, error) {
	listener, err := net.Listen("tcp", address)
	if err != nil {
		return httpEndpoint{}, fmt.Errorf(localization.Text("%s cannot listen on %s: %w"), name, address, err)
	}

	return httpEndpoint{
		name: name,
		server: &http.Server{
			Addr:              address,
			Handler:           handler,
			ReadHeaderTimeout: 10 * time.Second,
			TLSConfig:         tlsConfig,
		},
		listener:   listener,
		managedTLS: tlsConfig != nil,
	}, nil
}

func (endpoint httpEndpoint) serve() error {
	if endpoint.managedTLS {
		return endpoint.server.ServeTLS(endpoint.listener, "", "")
	}

	return endpoint.server.Serve(endpoint.listener)
}

func logHTTPReady(cfg *config) {
	serviceConfig := cfg.runtimeConfig
	attributes := []any{
		"public_url", serviceConfig.PublicURL,
		"tls_mode", serviceConfig.TLSMode,
		"http_address", serviceConfig.HTTPAddress,
	}
	if serviceConfig.TLSMode == runtimeconfig.TLSModeManaged {
		attributes = append(
			attributes,
			"https_address", serviceConfig.HTTPSAddress,
			"certificate_cache", cfg.certificateCachePath,
		)
	}
	if serviceConfig.TLSMode == runtimeconfig.TLSModeExternal {
		attributes = append(attributes, "trusted_proxies", serviceConfig.TrustedProxies)
		if len(serviceConfig.TrustedProxies) == 1 && serviceConfig.TrustedProxies[0] == "*" {
			slog.Warn(localization.Text("external TLS trusts all proxy sources"), "public_url", serviceConfig.PublicURL)
		}
	}
	if serviceConfig.TLSMode == runtimeconfig.TLSModePlain &&
		!strings.HasPrefix(serviceConfig.HTTPAddress, "127.0.0.1:") &&
		!strings.HasPrefix(serviceConfig.HTTPAddress, "[::1]:") &&
		!strings.HasPrefix(serviceConfig.HTTPAddress, "localhost:") {
		slog.Warn(localization.Text("plain HTTP is listening on a non-loopback address"), "http_address", serviceConfig.HTTPAddress)
	}
	slog.Info(localization.Text("web service is ready"), attributes...)
}

// canonicalHTTPSRedirect 将非 challenge 请求重定向到规范公网地址。
func canonicalHTTPSRedirect(publicURL string) http.Handler {
	return http.HandlerFunc(func(writer http.ResponseWriter, request *http.Request) {
		writer.Header().Set("Location", publicURL+request.URL.RequestURI())
		writer.WriteHeader(http.StatusPermanentRedirect)
	})
}

type certificateCache struct {
	autocert.Cache
	domain string
}

// Put 保存 ACME 数据并记录证书签发或续期结果。
func (cache certificateCache) Put(ctx context.Context, key string, data []byte) error {
	err := cache.Cache.Put(ctx, key, data)
	if key != cache.domain {
		return err
	}
	if err != nil {
		slog.Warn(localization.Text("failed to save the automatic HTTPS certificate"), "domain", cache.domain, "error", err)
	} else {
		slog.Info(localization.Text("automatic HTTPS certificate saved"), "domain", cache.domain)
	}

	return err
}

func logCertificateCacheState(ctx context.Context, cache autocert.Cache, domain string) {
	_, err := cache.Get(ctx, domain)
	switch {
	case err == nil:
		slog.Info(localization.Text("automatic HTTPS certificate cache detected"), "domain", domain)
	case errors.Is(err, autocert.ErrCacheMiss):
		slog.Info(localization.Text("automatic HTTPS certificate was not found; it will be requested when a TLS request arrives"), "domain", domain)
	default:
		slog.Warn(localization.Text("failed to read automatic HTTPS certificate cache"), "domain", domain, "error", err)
	}
}
