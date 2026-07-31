<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 接待会话面向访客使用的语言。
 */
enum ReceptionLanguage: string implements LabeledEnum
{
    case ChineseSimplified = 'zh-CN';
    case English = 'en';

    public function label(): string
    {
        return match ($this) {
            self::ChineseSimplified => __('channel.reception_languages.zh-CN'),
            self::English => __('channel.reception_languages.en'),
        };
    }
}
