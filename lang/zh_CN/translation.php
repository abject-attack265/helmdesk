<?php

return [
    'protocols' => [
        'google_translate' => 'Google Translate',
        'deepl' => 'DeepL',
        'azure_translator' => 'Microsoft Azure Translator',
        'baidu_translate' => '百度翻译',
        'tencent_cloud_translate' => '腾讯云机器翻译',
        'amazon_translate' => 'Amazon Translate',
        'deepseek' => 'DeepSeek V4 Flash',
    ],

    'check_succeeded' => '翻译测试成功',
    'reply_translation_required' => '请先确认发送给访客的译文。',

    'reception_languages' => [
        'zh-CN' => '简体中文',
        'en' => '英语',
    ],

    'driver_errors' => [
        'no_default_provider' => '系统未配置可用的翻译供应商。',
        'missing_credential' => ':provider 供应商缺少 :field 凭据。',
        'connection_failed' => ':provider 请求失败：:message',
        'upstream_error' => ':provider 返回错误：:message',
        'missing_translations_payload' => ':provider 响应缺少 translations 字段。',
        'target_language_mismatch' => ':provider 返回了错误的目标语言，预期 :expected，实际 :actual。',
    ],
];
