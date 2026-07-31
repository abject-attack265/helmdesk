package localization

import (
	"fmt"
	"os"
)

type language uint32

const (
	english language = iota
	simplifiedChinese
)

var currentLanguage = english

// Configure 优先使用命令行语言，其次读取 HELMDESK_LANG，未配置时使用英文。
func Configure(explicit string) error {
	currentLanguage = english

	value := explicit
	errorFormat := "unsupported language %q; supported languages are en and zh-CN"
	if value == "" {
		value = os.Getenv("HELMDESK_LANG")
		errorFormat = "invalid HELMDESK_LANG value %q; supported languages are en and zh-CN"
	}
	if value == "" {
		return nil
	}

	selected, ok := parseLanguage(value)
	if !ok {
		return fmt.Errorf(errorFormat, value)
	}
	currentLanguage = selected

	return nil
}

// Text 返回当前语言文案，中文翻译缺失时立即终止。
func Text(message string) string {
	if currentLanguage == english {
		return message
	}

	translated, exists := simplifiedChineseMessages[message]
	if !exists {
		panic(fmt.Sprintf("missing Simplified Chinese translation for %q", message))
	}

	return translated
}

// New 创建按当前输出语言呈现的固定错误。
func New(message string) error {
	return localizedError(message)
}

type localizedError string

// Error 返回当前语言对应的错误信息。
func (err localizedError) Error() string {
	return Text(string(err))
}

func parseLanguage(value string) (language, bool) {
	switch value {
	case "en":
		return english, true
	case "zh-CN":
		return simplifiedChinese, true
	default:
		return english, false
	}
}
