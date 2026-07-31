// Package updater 负责从 HelmDesk GitHub Release 获取并校验升级产物。
package updater

import (
	"context"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"log/slog"
	"net/http"
	"os"
	"strings"
	"time"

	"helmdesk/internal/buildinfo"
	"helmdesk/internal/localization"
)

const (
	defaultReleaseURL = "https://api.github.com/repos/helmdesk-ai/helmdesk/releases/latest"
	maxReleaseSize    = 4 << 20
	maxArtifactSize   = 2 << 30
)

// Client 访问 GitHub Release 并下载与当前平台匹配的产物。
type Client struct {
	HTTPClient *http.Client
	ReleaseURL string
	UserAgent  string
}

// Update 描述已通过发布元信息校验的目标版本。
type Update struct {
	Version string
	Asset   Asset
}

// Asset 描述 GitHub Release 中的单个升级产物。
type Asset struct {
	Name        string
	DownloadURL string
	Size        int64
	SHA256      string
}

type githubRelease struct {
	TagName   string        `json:"tag_name"`
	Immutable bool          `json:"immutable"`
	Assets    []githubAsset `json:"assets"`
}

type githubAsset struct {
	Name               string `json:"name"`
	BrowserDownloadURL string `json:"browser_download_url"`
	Size               int64  `json:"size"`
	Digest             string `json:"digest"`
}

// Check 返回当前平台可用的最新正式版本。
func (client Client) Check(ctx context.Context, currentVersion, operatingSystem, architecture string) (Update, bool, error) {
	release, err := client.latestRelease(ctx)
	if err != nil {
		return Update{}, false, err
	}
	if !release.Immutable {
		return Update{}, false, errors.New("GitHub latest release is not immutable")
	}
	targetVersion, found := strings.CutPrefix(release.TagName, "v")
	if !found {
		return Update{}, false, fmt.Errorf("GitHub release tag %q must start with v", release.TagName)
	}
	comparison, err := buildinfo.CompareVersions(targetVersion, currentVersion)
	if err != nil {
		return Update{}, false, fmt.Errorf("failed to compare release versions: %w", err)
	}
	if comparison <= 0 {
		return Update{Version: targetVersion}, false, nil
	}
	assetName, err := platformAssetName(operatingSystem, architecture)
	if err != nil {
		return Update{}, false, err
	}
	for _, releaseAsset := range release.Assets {
		if releaseAsset.Name != assetName {
			continue
		}
		asset, err := normalizeAsset(releaseAsset)
		if err != nil {
			return Update{}, false, err
		}
		return Update{Version: targetVersion, Asset: asset}, true, nil
	}
	return Update{}, false, fmt.Errorf("release %s does not contain %s", targetVersion, assetName)
}

// Download 将升级产物下载到指定目录并校验长度与 SHA-256。
func (client Client) Download(ctx context.Context, update Update, directory string) (string, error) {
	if update.Asset.Name == "" || update.Asset.DownloadURL == "" || update.Asset.Size <= 0 || update.Asset.SHA256 == "" {
		return "", errors.New("update asset is incomplete")
	}
	if err := os.MkdirAll(directory, 0755); err != nil {
		return "", err
	}
	file, err := os.CreateTemp(directory, ".helmdesk-upgrade-*")
	if err != nil {
		return "", err
	}
	path := file.Name()
	cleanup := func() {
		if err := file.Close(); err != nil {
			slog.Warn(localization.Text("failed to close incomplete update file"), "path", path, "error", err)
		}
		removeUpdateFile(path)
	}
	request, err := client.request(ctx, update.Asset.DownloadURL)
	if err != nil {
		cleanup()
		return "", err
	}
	response, err := client.httpClient().Do(request)
	if err != nil {
		cleanup()
		return "", fmt.Errorf("failed to download update asset: %w", err)
	}
	defer response.Body.Close()
	if response.StatusCode != http.StatusOK {
		cleanup()
		return "", fmt.Errorf("update asset returned %s", response.Status)
	}
	if response.ContentLength >= 0 && response.ContentLength != update.Asset.Size {
		cleanup()
		return "", fmt.Errorf("update asset size is %d bytes, expected %d", response.ContentLength, update.Asset.Size)
	}
	hash := sha256.New()
	written, err := io.Copy(io.MultiWriter(file, hash), io.LimitReader(response.Body, update.Asset.Size+1))
	if err != nil {
		cleanup()
		return "", fmt.Errorf("failed to write update asset: %w", err)
	}
	if written != update.Asset.Size {
		cleanup()
		return "", fmt.Errorf("downloaded update asset is %d bytes, expected %d", written, update.Asset.Size)
	}
	actualDigest := hex.EncodeToString(hash.Sum(nil))
	if !strings.EqualFold(actualDigest, update.Asset.SHA256) {
		cleanup()
		return "", fmt.Errorf("update asset SHA-256 is %s, expected %s", actualDigest, update.Asset.SHA256)
	}
	if err := file.Chmod(0755); err != nil {
		cleanup()
		return "", err
	}
	if err := file.Sync(); err != nil {
		cleanup()
		return "", err
	}
	if err := file.Close(); err != nil {
		removeUpdateFile(path)
		return "", err
	}
	return path, nil
}

// removeUpdateFile 删除校验失败的升级文件。
func removeUpdateFile(path string) {
	if err := os.Remove(path); err != nil && !errors.Is(err, os.ErrNotExist) {
		slog.Warn(localization.Text("failed to remove incomplete update file"), "path", path, "error", err)
	}
}

// latestRelease 读取 GitHub 最新 Release 并限制响应大小。
func (client Client) latestRelease(ctx context.Context) (githubRelease, error) {
	releaseURL := client.ReleaseURL
	if releaseURL == "" {
		releaseURL = defaultReleaseURL
	}
	request, err := client.request(ctx, releaseURL)
	if err != nil {
		return githubRelease{}, err
	}
	response, err := client.httpClient().Do(request)
	if err != nil {
		return githubRelease{}, fmt.Errorf("failed to check the latest release: %w", err)
	}
	defer response.Body.Close()
	if response.StatusCode != http.StatusOK {
		return githubRelease{}, fmt.Errorf("latest release request returned %s", response.Status)
	}
	var release githubRelease
	decoder := json.NewDecoder(io.LimitReader(response.Body, maxReleaseSize))
	if err := decoder.Decode(&release); err != nil {
		return githubRelease{}, fmt.Errorf("failed to decode the latest release: %w", err)
	}
	return release, nil
}

// request 创建 GitHub API 和资产下载共用的 HTTP 请求。
func (client Client) request(ctx context.Context, url string) (*http.Request, error) {
	request, err := http.NewRequestWithContext(ctx, http.MethodGet, url, nil)
	if err != nil {
		return nil, err
	}
	userAgent := client.UserAgent
	if userAgent == "" {
		userAgent = "HelmDesk/" + buildinfo.Current().Version
	}
	request.Header.Set("User-Agent", userAgent)
	request.Header.Set("Accept", "application/vnd.github+json")
	request.Header.Set("X-GitHub-Api-Version", "2026-03-10")
	return request, nil
}

// httpClient 返回显式配置或带整体超时的默认 HTTP 客户端。
func (client Client) httpClient() *http.Client {
	if client.HTTPClient != nil {
		return client.HTTPClient
	}
	return &http.Client{Timeout: 30 * time.Minute}
}

// normalizeAsset 校验 GitHub 资产摘要和下载边界。
func normalizeAsset(asset githubAsset) (Asset, error) {
	if asset.Size <= 0 || asset.Size > maxArtifactSize {
		return Asset{}, fmt.Errorf("release asset %s has invalid size %d", asset.Name, asset.Size)
	}
	algorithm, digest, found := strings.Cut(asset.Digest, ":")
	if !found || algorithm != "sha256" || len(digest) != sha256.Size*2 {
		return Asset{}, fmt.Errorf("release asset %s does not have a valid SHA-256 digest", asset.Name)
	}
	if _, err := hex.DecodeString(digest); err != nil {
		return Asset{}, fmt.Errorf("release asset %s does not have a valid SHA-256 digest", asset.Name)
	}
	if asset.BrowserDownloadURL == "" {
		return Asset{}, fmt.Errorf("release asset %s does not have a download URL", asset.Name)
	}
	return Asset{
		Name:        asset.Name,
		DownloadURL: asset.BrowserDownloadURL,
		Size:        asset.Size,
		SHA256:      strings.ToLower(digest),
	}, nil
}

// platformAssetName 返回当前安装方式使用的正式发布资产名。
func platformAssetName(operatingSystem, architecture string) (string, error) {
	switch operatingSystem {
	case "linux":
		if architecture != "amd64" && architecture != "arm64" {
			return "", fmt.Errorf("automatic upgrade does not support linux/%s", architecture)
		}
		return "helmdesk-linux-" + architecture, nil
	case "windows":
		if architecture != "amd64" {
			return "", fmt.Errorf("automatic upgrade does not support windows/%s", architecture)
		}
		return "HelmDesk-Setup.exe", nil
	default:
		return "", fmt.Errorf("automatic upgrade does not support %s/%s", operatingSystem, architecture)
	}
}
