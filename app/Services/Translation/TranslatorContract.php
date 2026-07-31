<?php

namespace App\Services\Translation;

use App\Services\Translation\Exceptions\TranslationException;

/**
 * 为机器翻译和大模型翻译提供统一调用契约。
 */
interface TranslatorContract
{
    /**
     * 翻译一段文本。
     *
     * @param  string  $text  待翻译原文，不能为空字符串（空字符串调用方应自行短路）
     * @param  string  $sourceLang  源语言 BCP-47 标签，例如 "en"、"zh-CN"；传 "auto" 表示让供应商自动检测
     * @param  string  $targetLang  目标语言 BCP-47 标签
     * @param  array<string, mixed>  $options  驱动可选参数
     *
     * @throws TranslationException
     */
    public function translate(string $text, string $sourceLang, string $targetLang, array $options = []): TranslationResult;
}
