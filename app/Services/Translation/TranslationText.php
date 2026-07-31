<?php

namespace App\Services\Translation;

/**
 * 判断文本是否包含值得交给翻译供应商处理的语言文字。
 */
final class TranslationText
{
    /**
     * 判断文本是否至少包含一个 Unicode 字母。
     */
    public static function hasTranslatableLetters(string $text): bool
    {
        return preg_match('/\p{L}/u', $text) === 1;
    }
}
