<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 翻译供应商协议，覆盖机器翻译 API 与用于翻译的大模型 API。
 */
enum TranslationProviderType: string implements LabeledEnum
{
    case GoogleTranslate = 'google-translate';
    case DeepL = 'deepl';
    case AzureTranslator = 'azure-translator';
    case BaiduTranslate = 'baidu-translate';
    case TencentCloudTranslate = 'tencent-cloud-translate';
    case AmazonTranslate = 'amazon-translate';
    case DeepSeek = 'deepseek';

    /**
     * 返回协议的本地化名称。
     */
    public function label(): string
    {
        return match ($this) {
            self::GoogleTranslate => __('translation.protocols.google_translate'),
            self::DeepL => __('translation.protocols.deepl'),
            self::AzureTranslator => __('translation.protocols.azure_translator'),
            self::BaiduTranslate => __('translation.protocols.baidu_translate'),
            self::TencentCloudTranslate => __('translation.protocols.tencent_cloud_translate'),
            self::AmazonTranslate => __('translation.protocols.amazon_translate'),
            self::DeepSeek => __('translation.protocols.deepseek'),
        };
    }

    /**
     * 判断协议是否使用大模型，供翻译池按调用策略分组排序。
     */
    public function isLlm(): bool
    {
        return $this === self::DeepSeek;
    }
}
