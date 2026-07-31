package updater

import (
	"context"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"net/http"
	"net/http/httptest"
	"os"
	"testing"
)

func TestCheckSelectsImmutablePlatformAssetAndDownloadVerifiesIt(t *testing.T) {
	contents := []byte("helmdesk release artifact")
	digest := sha256.Sum256(contents)
	server := httptest.NewServer(http.HandlerFunc(func(writer http.ResponseWriter, request *http.Request) {
		switch request.URL.Path {
		case "/latest":
			writer.Header().Set("Content-Type", "application/json")
			fmt.Fprintf(
				writer,
				`{"tag_name":"v1.3.0","immutable":true,"assets":[{"name":"helmdesk-linux-amd64","browser_download_url":"%s/artifact","size":%d,"digest":"sha256:%s"}]}`,
				serverURL(request),
				len(contents),
				hex.EncodeToString(digest[:]),
			)
		case "/artifact":
			writer.Write(contents)
		default:
			http.NotFound(writer, request)
		}
	}))
	defer server.Close()

	client := Client{HTTPClient: server.Client(), ReleaseURL: server.URL + "/latest"}
	update, available, err := client.Check(context.Background(), "1.2.0", "linux", "amd64")
	if err != nil {
		t.Fatal(err)
	}
	if !available || update.Version != "1.3.0" {
		t.Fatalf("Check() = %#v, %t，期望版本 1.3.0", update, available)
	}
	path, err := client.Download(context.Background(), update, t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	actual, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}
	if string(actual) != string(contents) {
		t.Fatalf("下载内容为 %q，期望 %q", actual, contents)
	}
}

func TestCheckRequiresReleaseTagPrefix(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(writer http.ResponseWriter, _ *http.Request) {
		fmt.Fprint(writer, `{"tag_name":"1.3.0","immutable":true}`)
	}))
	defer server.Close()

	client := Client{HTTPClient: server.Client(), ReleaseURL: server.URL}
	if _, _, err := client.Check(context.Background(), "1.2.0", "linux", "amd64"); err == nil {
		t.Fatal("Check() 应拒绝不带 v 前缀的 Release 标签")
	}
}

func TestCheckRejectsMutableRelease(t *testing.T) {
	server := httptest.NewServer(http.HandlerFunc(func(writer http.ResponseWriter, _ *http.Request) {
		fmt.Fprint(writer, `{"tag_name":"v1.3.0","immutable":false}`)
	}))
	defer server.Close()

	client := Client{HTTPClient: server.Client(), ReleaseURL: server.URL}
	if _, _, err := client.Check(context.Background(), "1.2.0", "linux", "amd64"); err == nil {
		t.Fatal("Check() 应拒绝可变 Release")
	}
}

func TestDownloadRejectsDigestMismatch(t *testing.T) {
	contents := []byte("tampered")
	server := httptest.NewServer(http.HandlerFunc(func(writer http.ResponseWriter, _ *http.Request) {
		writer.Write(contents)
	}))
	defer server.Close()

	client := Client{HTTPClient: server.Client()}
	_, err := client.Download(context.Background(), Update{
		Version: "1.3.0",
		Asset: Asset{
			Name:        "helmdesk-linux-amd64",
			DownloadURL: server.URL,
			Size:        int64(len(contents)),
			SHA256:      string(make([]byte, 64)),
		},
	}, t.TempDir())
	if err == nil {
		t.Fatal("Download() 应拒绝摘要不匹配的文件")
	}
}

func serverURL(request *http.Request) string {
	return "http://" + request.Host
}
