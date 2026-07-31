// Package buildinfo 提供发布构建注入的版本和来源信息。
package buildinfo

import (
	"fmt"
	"runtime"
	"strings"
)

var (
	// Version 是发布版本号，由构建流程通过 ldflags 注入。
	Version = "0.0.0-dev"
	// Commit 是构建对应的 Git 提交，由构建流程通过 ldflags 注入。
	Commit = "unknown"
	// BuildDate 是 UTC 构建时间，由构建流程通过 ldflags 注入。
	BuildDate = "unknown"
)

// Info 描述当前 HelmDesk 可执行文件的构建身份。
type Info struct {
	Version   string `json:"version"`
	Commit    string `json:"commit"`
	BuildDate string `json:"build_date"`
	OS        string `json:"os"`
	Arch      string `json:"arch"`
}

// Current 返回当前可执行文件的构建身份。
func Current() Info {
	return Info{
		Version:   Version,
		Commit:    Commit,
		BuildDate: BuildDate,
		OS:        runtime.GOOS,
		Arch:      runtime.GOARCH,
	}
}

// CompareVersions 按 SemVer 优先级比较两个版本。
func CompareVersions(left, right string) (int, error) {
	leftVersion, err := parseVersion(left)
	if err != nil {
		return 0, err
	}
	rightVersion, err := parseVersion(right)
	if err != nil {
		return 0, err
	}
	for index := range 3 {
		if comparison := compareNumericIdentifier(leftVersion.core[index], rightVersion.core[index]); comparison != 0 {
			return comparison, nil
		}
	}
	return comparePrerelease(leftVersion.prerelease, rightVersion.prerelease), nil
}

type semanticVersion struct {
	core       [3]string
	prerelease []string
}

// parseVersion 解析升级比较所需的 SemVer 核心字段和预发布标识。
func parseVersion(value string) (semanticVersion, error) {
	if value == "" {
		return semanticVersion{}, fmt.Errorf("version cannot be empty")
	}
	if strings.Count(value, "+") > 1 {
		return semanticVersion{}, fmt.Errorf("invalid semantic version %q", value)
	}
	withoutBuild, build, hasBuild := strings.Cut(value, "+")
	if hasBuild && !validIdentifiers(build, false) {
		return semanticVersion{}, fmt.Errorf("invalid semantic version %q", value)
	}
	coreValue, prereleaseValue, hasPrerelease := strings.Cut(withoutBuild, "-")
	coreParts := strings.Split(coreValue, ".")
	if len(coreParts) != 3 {
		return semanticVersion{}, fmt.Errorf("invalid semantic version %q", value)
	}
	var parsed semanticVersion
	for index, part := range coreParts {
		if !numericIdentifier(part) || (len(part) > 1 && part[0] == '0') {
			return semanticVersion{}, fmt.Errorf("invalid semantic version %q", value)
		}
		parsed.core[index] = part
	}
	if !hasPrerelease {
		return parsed, nil
	}
	if !validIdentifiers(prereleaseValue, true) {
		return semanticVersion{}, fmt.Errorf("invalid semantic version %q", value)
	}
	parsed.prerelease = strings.Split(prereleaseValue, ".")
	return parsed, nil
}

// validIdentifiers 校验 SemVer 预发布或构建标识。
func validIdentifiers(value string, rejectNumericLeadingZero bool) bool {
	for _, identifier := range strings.Split(value, ".") {
		if identifier == "" || (rejectNumericLeadingZero && len(identifier) > 1 && identifier[0] == '0' && numericIdentifier(identifier)) {
			return false
		}
		for _, character := range identifier {
			if (character < '0' || character > '9') &&
				(character < 'A' || character > 'Z') &&
				(character < 'a' || character > 'z') &&
				character != '-' {
				return false
			}
		}
	}
	return true
}

// comparePrerelease 按 SemVer 规则比较预发布标识。
func comparePrerelease(left, right []string) int {
	if len(left) == 0 && len(right) == 0 {
		return 0
	}
	if len(left) == 0 {
		return 1
	}
	if len(right) == 0 {
		return -1
	}
	for index := 0; index < min(len(left), len(right)); index++ {
		leftIdentifier := left[index]
		rightIdentifier := right[index]
		leftNumeric := numericIdentifier(leftIdentifier)
		rightNumeric := numericIdentifier(rightIdentifier)
		switch {
		case leftNumeric && rightNumeric:
			if comparison := compareNumericIdentifier(leftIdentifier, rightIdentifier); comparison != 0 {
				return comparison
			}
		case leftNumeric:
			return -1
		case rightNumeric:
			return 1
		case leftIdentifier < rightIdentifier:
			return -1
		case leftIdentifier > rightIdentifier:
			return 1
		}
	}
	switch {
	case len(left) < len(right):
		return -1
	case len(left) > len(right):
		return 1
	default:
		return 0
	}
}

// compareNumericIdentifier 比较任意长度的十进制 SemVer 数值。
func compareNumericIdentifier(left, right string) int {
	switch {
	case len(left) < len(right):
		return -1
	case len(left) > len(right):
		return 1
	case left < right:
		return -1
	case left > right:
		return 1
	default:
		return 0
	}
}

// numericIdentifier 判断预发布标识是否完全由数字组成。
func numericIdentifier(value string) bool {
	for _, character := range value {
		if character < '0' || character > '9' {
			return false
		}
	}
	return value != ""
}
