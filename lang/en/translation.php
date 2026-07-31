<?php

return [
    'protocols' => [
        'google_translate' => 'Google Translate',
        'deepl' => 'DeepL',
        'azure_translator' => 'Microsoft Azure Translator',
        'baidu_translate' => 'Baidu Translate',
        'tencent_cloud_translate' => 'Tencent Cloud Machine Translation',
        'amazon_translate' => 'Amazon Translate',
        'deepseek' => 'DeepSeek V4 Flash',
    ],

    'check_succeeded' => 'Translation test succeeded.',
    'reply_translation_required' => 'Confirm the visitor-facing translation before sending.',

    'reception_languages' => [
        'zh-CN' => 'Simplified Chinese',
        'en' => 'English',
    ],

    'driver_errors' => [
        'no_default_provider' => 'No usable translation provider is configured.',
        'missing_credential' => ':provider provider is missing :field credential.',
        'connection_failed' => ':provider request failed: :message',
        'upstream_error' => ':provider returned an error: :message',
        'missing_translations_payload' => ':provider response is missing translations payload.',
        'target_language_mismatch' => ':provider returned the wrong target language; expected :expected, received :actual.',
    ],
];
