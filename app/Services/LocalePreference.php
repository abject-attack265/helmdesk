<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * 统一前后端语言偏好的标准化、请求解析和浏览器语言识别。
 */
class LocalePreference
{
    /**
     * 语言标签允许的基础 BCP 47 形态。
     */
    public const string LANGUAGE_TAG_PATTERN = '/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/';

    public const string DEFAULT_FRONTEND_LOCALE = 'zh-CN';

    public const string DEFAULT_LARAVEL_LOCALE = 'zh_CN';

    /**
     * 返回前端当前支持的语言列表。
     *
     * @return list<string>
     */
    public static function frontendLocales(): array
    {
        return [self::DEFAULT_FRONTEND_LOCALE, 'en'];
    }

    /**
     * 将任意语言值标准化为前端语言标识。
     */
    public static function normalizeFrontend(?string $locale): string
    {
        $locale = trim((string) $locale);

        if ($locale === '') {
            return self::DEFAULT_FRONTEND_LOCALE;
        }

        $normalized = str_replace('_', '-', $locale);
        $lower = strtolower($normalized);

        if ($lower === 'en' || str_starts_with($lower, 'en-')) {
            return 'en';
        }

        if ($lower === 'zh' || str_starts_with($lower, 'zh-')) {
            return self::DEFAULT_FRONTEND_LOCALE;
        }

        return self::DEFAULT_FRONTEND_LOCALE;
    }

    /**
     * 将任意语言值标准化为 Laravel 语言目录标识。
     */
    public static function normalizeLaravel(?string $locale): string
    {
        return match (self::normalizeFrontend($locale)) {
            'en' => 'en',
            default => self::DEFAULT_LARAVEL_LOCALE,
        };
    }

    /**
     * 将 Laravel 语言标识转换成前端语言标识。
     */
    public static function frontendFromLaravel(?string $locale): string
    {
        return self::normalizeFrontend(str_replace('_', '-', (string) $locale));
    }

    /**
     * 判断两个 locale 是否可视为同一语言。
     */
    public static function matches(string $first, string $second): bool
    {
        $left = strtolower(str_replace('_', '-', trim($first)));
        $right = strtolower(str_replace('_', '-', trim($second)));

        if ($left === '' || $right === '') {
            return false;
        }

        if ($left === $right) {
            return true;
        }

        return explode('-', $left)[0] === explode('-', $right)[0];
    }

    /**
     * 从请求参数、Cookie 或浏览器偏好中解析前端语言标识。
     */
    public static function fromRequest(Request $request): string
    {
        return self::normalizeFrontend(
            $request->input('locale')
                ?? $request->cookie('locale')
                ?? self::preferredBrowserLocale($request)
        );
    }

    /**
     * 从 Accept-Language 请求头中选出最接近的受支持语言。
     */
    public static function preferredBrowserLocale(Request $request): ?string
    {
        if (! $request->headers->has('Accept-Language')) {
            return null;
        }

        return $request->getPreferredLanguage(self::frontendLocales());
    }

    /**
     * 从 Accept-Language 头中读取首个符合基础格式的语言标签。
     */
    public static function firstAcceptedLanguage(?string $value): ?string
    {
        foreach (explode(',', (string) $value) as $part) {
            $language = trim(explode(';', $part, 2)[0]);
            if ($language !== '' && preg_match(self::LANGUAGE_TAG_PATTERN, $language) === 1) {
                return $language;
            }
        }

        return null;
    }

    /**
     * 从 Accept-Language 头中读取首个非空语言值，不校验标签格式。
     */
    public static function firstAcceptedLanguageValue(?string $value): ?string
    {
        foreach (explode(',', (string) $value) as $part) {
            $language = trim(explode(';', $part, 2)[0]);
            if ($language !== '') {
                return $language;
            }
        }

        return null;
    }
}
