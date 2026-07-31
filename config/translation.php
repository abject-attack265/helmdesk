<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 翻译服务运行参数
    |--------------------------------------------------------------------------
    |
    | 给 app/Services/Translation/** 共用的运行参数。具体翻译供应商的凭据 / 启停状态由
    | translation_providers 表管理。
    |
    */

    'request_timeout' => (int) env('TRANSLATION_REQUEST_TIMEOUT', 5),

    // DeepSeek 翻译使用独立的单次请求超时。
    'llm_request_timeout' => (int) env('TRANSLATION_LLM_REQUEST_TIMEOUT', 30),
];
