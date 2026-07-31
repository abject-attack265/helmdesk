package runtime

import (
	"net/http"
	"strings"

	"helmdesk/internal/localization"
)

const maxLocalUploadBytes int64 = 100 * 1024 * 1024

// limitLocalUploadBody 在请求进入 PHP 前限制本地附件直传的实际请求体。
func limitLocalUploadBody(next http.Handler) http.Handler {
	return http.HandlerFunc(func(writer http.ResponseWriter, request *http.Request) {
		if request.Method != http.MethodPut ||
			!strings.HasPrefix(request.URL.Path, "/storage/") ||
			request.URL.Query().Get("upload") != "1" {
			next.ServeHTTP(writer, request)
			return
		}

		if request.ContentLength < 0 {
			http.Error(writer, localization.Text("attachment uploads require Content-Length"), http.StatusLengthRequired)
			return
		}
		if request.ContentLength > maxLocalUploadBytes {
			http.Error(writer, localization.Text("attachment size exceeds the upload limit"), http.StatusRequestEntityTooLarge)
			return
		}

		request.Body = http.MaxBytesReader(writer, request.Body, maxLocalUploadBytes)
		next.ServeHTTP(writer, request)
	})
}
