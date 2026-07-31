<?php

namespace App\Services\Channel;

/**
 * 网站渠道统一主题色板。
 *
 * 预设色板作为表单 options 下发，供前端渲染快捷选色按钮；
 * 主题色本身允许任意 6 位 hex 自定义，校验与归一化统一走 HEX_PATTERN。
 */
class WebChannelThemePalette
{
    /**
     * 默认主题色（蓝）。
     */
    public const string DEFAULT = '#2563EB';

    /**
     * 主题色 hex 校验正则（# + 6 位十六进制），与前端拾色器输出保持一致。
     */
    public const string HEX_PATTERN = '/^#[0-9A-Fa-f]{6}$/';

    /**
     * 返回全部预设主题色（hex 字符串），顺序即色板展示顺序。
     *
     * @return list<string>
     */
    public static function presets(): array
    {
        return [
            self::DEFAULT, // 蓝，默认
            '#14B8A6',     // 青绿
            '#F97316',     // 橙
            '#3B82F6',     // 天蓝
            '#F59E0B',     // 琥珀
            '#B91CBA',     // 品红紫
            '#D4B106',     // 金黄
            '#7C3AED',     // 紫
            '#65A30D',     // 绿
            '#C2185B',     // 玫红
            '#FFDC21',     // 明黄
            '#020617',     // 墨黑
        ];
    }

    /**
     * 校验是否为合法的 6 位 hex 主题色。
     */
    public static function isValidColor(string $value): bool
    {
        return preg_match(self::HEX_PATTERN, $value) === 1;
    }

    /**
     * 归一化主题色：合法 hex 统一为大写 #RRGGBB，非法值回退默认色。
     */
    public static function normalize(string $value): string
    {
        return self::isValidColor($value) ? strtoupper($value) : self::DEFAULT;
    }
}
