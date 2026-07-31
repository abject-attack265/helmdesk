package backup

import (
	"archive/tar"
	"bytes"
	"compress/gzip"
	"crypto/sha256"
	"encoding/hex"
	"encoding/json"
	"errors"
	"fmt"
	"io"
	"io/fs"
	"log/slog"
	"os"
	"path"
	"path/filepath"
	"sort"
	"strings"
	"time"

	"helmdesk/internal/localization"
)

const maxManifestSize = 1024 * 1024

// writeArchive 将暂存目录原子写入 gzip 压缩的 tar 文件。
func writeArchive(stagePath, outputPath string, createdAt time.Time) error {
	tempFile, err := os.CreateTemp(filepath.Dir(outputPath), ".helmdesk-archive-*")
	if err != nil {
		return err
	}
	tempPath := tempFile.Name()
	defer func() {
		if err := os.Remove(tempPath); err != nil && !errors.Is(err, os.ErrNotExist) {
			slog.Warn(localization.Text("failed to clean up incomplete backup archive"), "path", tempPath, "error", err)
		}
	}()
	if err := tempFile.Chmod(0600); err != nil {
		tempFile.Close()
		return err
	}
	gzipWriter := gzip.NewWriter(tempFile)
	gzipWriter.Header.ModTime = createdAt
	tarWriter := tar.NewWriter(gzipWriter)

	paths, err := archivePaths(stagePath)
	if err == nil {
		for _, relative := range paths {
			if err = writeArchiveEntry(tarWriter, stagePath, relative); err != nil {
				break
			}
		}
	}
	if closeErr := tarWriter.Close(); err == nil {
		err = closeErr
	}
	if closeErr := gzipWriter.Close(); err == nil {
		err = closeErr
	}
	if syncErr := tempFile.Sync(); err == nil {
		err = syncErr
	}
	if closeErr := tempFile.Close(); err == nil {
		err = closeErr
	}
	if err != nil {
		return err
	}
	return os.Rename(tempPath, outputPath)
}

// archivePaths 返回 manifest 优先且其余条目稳定排序的归档路径。
func archivePaths(stagePath string) ([]string, error) {
	paths := make([]string, 0)
	err := filepath.WalkDir(stagePath, func(current string, entry fs.DirEntry, walkErr error) error {
		if walkErr != nil {
			return walkErr
		}
		relative, err := filepath.Rel(stagePath, current)
		if err != nil {
			return err
		}
		if relative == "." || relative == "manifest.json" {
			return nil
		}
		paths = append(paths, filepath.ToSlash(relative))
		return nil
	})
	if err != nil {
		return nil, err
	}
	sort.Strings(paths)
	return append([]string{"manifest.json"}, paths...), nil
}

func writeArchiveEntry(writer *tar.Writer, stagePath, relative string) error {
	source := filepath.Join(stagePath, filepath.FromSlash(relative))
	info, err := os.Lstat(source)
	if err != nil {
		return err
	}
	if !info.IsDir() && !info.Mode().IsRegular() {
		return fmt.Errorf(localization.Text("archive contains an unsupported file type: %s"), source)
	}
	header, err := tar.FileInfoHeader(info, "")
	if err != nil {
		return err
	}
	header.Name = relative
	if info.IsDir() {
		header.Name += "/"
	}
	if err := writer.WriteHeader(header); err != nil {
		return err
	}
	if info.IsDir() {
		return nil
	}
	file, err := os.Open(source)
	if err != nil {
		return err
	}
	defer file.Close()
	_, err = io.Copy(writer, file)
	return err
}

// extractArchive 提取备份并校验清单、路径和文件摘要。
func extractArchive(archivePath, destination string) (manifest, error) {
	file, err := os.Open(archivePath)
	if err != nil {
		return manifest{}, err
	}
	defer file.Close()
	gzipReader, err := gzip.NewReader(file)
	if err != nil {
		return manifest{}, fmt.Errorf(localization.Text("invalid backup compression format: %w"), err)
	}
	defer gzipReader.Close()
	reader := tar.NewReader(gzipReader)
	header, err := reader.Next()
	if err != nil {
		return manifest{}, fmt.Errorf(localization.Text("backup manifest is missing: %w"), err)
	}
	if header.Name != "manifest.json" || !header.FileInfo().Mode().IsRegular() || header.Size > maxManifestSize {
		return manifest{}, errors.New(localization.Text("the first backup entry must be a valid manifest.json"))
	}
	manifestContents, err := io.ReadAll(reader)
	if err != nil {
		return manifest{}, err
	}
	var backupManifest manifest
	decoder := json.NewDecoder(bytes.NewReader(manifestContents))
	decoder.DisallowUnknownFields()
	if err := decoder.Decode(&backupManifest); err != nil {
		return manifest{}, fmt.Errorf(localization.Text("invalid backup manifest: %w"), err)
	}
	if err := decoder.Decode(&struct{}{}); !errors.Is(err, io.EOF) {
		return manifest{}, errors.New(localization.Text("backup manifest contains trailing content"))
	}
	expected, err := validateManifest(backupManifest)
	if err != nil {
		return manifest{}, err
	}
	seen := make(map[string]struct{}, len(expected))
	for {
		header, err = reader.Next()
		if errors.Is(err, io.EOF) {
			break
		}
		if err != nil {
			return manifest{}, err
		}
		name := strings.TrimSuffix(header.Name, "/")
		if !validArchiveName(name) {
			return manifest{}, fmt.Errorf(localization.Text("backup contains an invalid path: %s"), header.Name)
		}
		if header.FileInfo().IsDir() {
			if !allowedArchiveDirectory(name) {
				return manifest{}, fmt.Errorf(localization.Text("backup contains a non-business directory: %s"), header.Name)
			}
			continue
		}
		if !header.FileInfo().Mode().IsRegular() {
			return manifest{}, fmt.Errorf(localization.Text("backup contains an unsupported file type: %s"), header.Name)
		}
		item, exists := expected[name]
		if !exists {
			return manifest{}, fmt.Errorf(localization.Text("backup contains a file not listed in the manifest: %s"), name)
		}
		if _, duplicate := seen[name]; duplicate {
			return manifest{}, fmt.Errorf(localization.Text("backup contains a duplicate file: %s"), name)
		}
		if header.Size != item.Size {
			return manifest{}, fmt.Errorf(localization.Text("backup file size does not match the manifest: %s"), name)
		}
		target := filepath.Join(destination, filepath.FromSlash(name))
		if err := os.MkdirAll(filepath.Dir(target), 0700); err != nil {
			return manifest{}, err
		}
		mode := os.FileMode(header.Mode) & 0777
		if name == "runtime/.env" {
			mode = 0600
		}
		output, err := os.OpenFile(target, os.O_CREATE|os.O_EXCL|os.O_WRONLY, mode)
		if err != nil {
			return manifest{}, err
		}
		hash := sha256.New()
		written, copyErr := io.Copy(io.MultiWriter(output, hash), reader)
		closeErr := output.Close()
		if copyErr != nil {
			return manifest{}, copyErr
		}
		if closeErr != nil {
			return manifest{}, closeErr
		}
		if written != item.Size || hex.EncodeToString(hash.Sum(nil)) != item.SHA256 {
			return manifest{}, fmt.Errorf(localization.Text("backup file checksum validation failed: %s"), name)
		}
		seen[name] = struct{}{}
	}
	if len(seen) != len(expected) {
		return manifest{}, errors.New(localization.Text("backup is missing files listed in the manifest"))
	}
	for _, directory := range []string{"files/private", "files/public"} {
		if err := os.MkdirAll(filepath.Join(destination, filepath.FromSlash(directory)), 0700); err != nil {
			return manifest{}, err
		}
	}
	for _, database := range []string{"database/main.sqlite", "database/rag.sqlite"} {
		if err := validateSQLiteHeader(filepath.Join(destination, filepath.FromSlash(database))); err != nil {
			return manifest{}, err
		}
	}
	return backupManifest, nil
}

// validateManifest 校验格式版本、必需文件和文件摘要。
func validateManifest(value manifest) (map[string]manifestFile, error) {
	if value.FormatVersion != formatVersion {
		return nil, fmt.Errorf(localization.Text("unsupported backup format version: %d"), value.FormatVersion)
	}
	if _, err := time.Parse(time.RFC3339, value.CreatedAt); err != nil {
		return nil, errors.New(localization.Text("backup manifest has an invalid created_at value"))
	}
	expected := make(map[string]manifestFile, len(value.Files))
	for _, item := range value.Files {
		if !validArchiveName(item.Path) || !allowedArchiveFile(item.Path) {
			return nil, fmt.Errorf(localization.Text("backup manifest contains a non-business file: %s"), item.Path)
		}
		if item.Size < 0 {
			return nil, fmt.Errorf(localization.Text("backup manifest contains an invalid file size: %s"), item.Path)
		}
		digest, err := hex.DecodeString(item.SHA256)
		if err != nil || len(digest) != sha256.Size {
			return nil, fmt.Errorf(localization.Text("backup manifest contains an invalid file checksum: %s"), item.Path)
		}
		if _, duplicate := expected[item.Path]; duplicate {
			return nil, fmt.Errorf(localization.Text("backup manifest contains a duplicate file: %s"), item.Path)
		}
		expected[item.Path] = item
	}
	for _, required := range []string{"database/main.sqlite", "database/rag.sqlite", "runtime/.env"} {
		if _, exists := expected[required]; !exists {
			return nil, fmt.Errorf(localization.Text("backup manifest is missing %s"), required)
		}
	}
	return expected, nil
}

// validArchiveName 拒绝绝对路径、反斜线和父目录跳转。
func validArchiveName(name string) bool {
	return name != "" &&
		name == path.Clean(name) &&
		!path.IsAbs(name) &&
		!strings.Contains(name, `\`) &&
		name != ".." &&
		!strings.HasPrefix(name, "../")
}

// allowedArchiveFile 限定备份中的业务文件范围。
func allowedArchiveFile(name string) bool {
	return name == "database/main.sqlite" ||
		name == "database/rag.sqlite" ||
		name == "runtime/.env" ||
		strings.HasPrefix(name, "files/private/") ||
		strings.HasPrefix(name, "files/public/")
}

// allowedArchiveDirectory 限定备份中的目录范围。
func allowedArchiveDirectory(name string) bool {
	return name == "database" ||
		name == "runtime" ||
		name == "files" ||
		name == "files/private" ||
		name == "files/public" ||
		strings.HasPrefix(name, "files/private/") ||
		strings.HasPrefix(name, "files/public/")
}
