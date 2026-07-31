package runtime

import (
	"errors"
	"log/slog"
	"net/http"
	"os"
	"path"
	"path/filepath"
	"strings"

	"helmdesk/internal/localization"

	"github.com/dunglas/frankenphp"
	"github.com/dunglas/mercure"
)

// phpHandler 直接响应静态文件并将应用请求交给 Laravel worker。
func phpHandler(cfg *config, hub *mercure.Hub) http.Handler {
	publicPath := filepath.Join(cfg.projectRoot, "public")
	documentRoot := frankenphp.WithRequestDocumentRoot(publicPath, false)

	return http.HandlerFunc(func(writer http.ResponseWriter, request *http.Request) {
		cleanPath := strings.TrimPrefix(path.Clean(request.URL.Path), "/")
		staticPath := filepath.Join(publicPath, filepath.FromSlash(cleanPath))
		info, statErr := os.Stat(staticPath)
		if statErr == nil && !info.IsDir() && !strings.EqualFold(filepath.Ext(staticPath), ".php") {
			http.ServeFile(writer, request, staticPath)
			return
		}
		if statErr != nil && !errors.Is(statErr, os.ErrNotExist) {
			slog.Error(localization.Text("failed to read static file"), "path", staticPath, "error", statErr)
			http.Error(writer, localization.Text("unable to read static file"), http.StatusInternalServerError)
			return
		}

		phpRequest := request.Clone(request.Context())
		frankenRequest, requestErr := frankenphp.NewRequestWithContext(
			phpRequest,
			documentRoot,
			frankenphp.WithWorkerName("web"),
			frankenphp.WithMercureHub(hub),
			frankenphp.WithRequestEnv(map[string]string{"REQUEST_URI": request.RequestURI}),
		)
		if requestErr != nil {
			slog.Error(localization.Text("failed to create PHP request"), "method", request.Method, "path", request.URL.Path, "error", requestErr)
			http.Error(writer, localization.Text("unable to process request"), http.StatusInternalServerError)
			return
		}
		if serveErr := frankenphp.ServeHTTP(writer, frankenRequest); serveErr != nil {
			slog.Error(localization.Text("PHP request failed"), "method", request.Method, "path", request.URL.Path, "error", serveErr)
		}
	})
}
