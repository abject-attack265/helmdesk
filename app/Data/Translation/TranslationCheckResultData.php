<?php

namespace App\Data\Translation;

use App\Services\Translation\TranslationResult;
use Spatie\LaravelData\Data;

/** 翻译供应商连接测试结果。 */
class TranslationCheckResultData extends Data
{
    /** 创建连接状态、提示和可选译文结果。 */
    public function __construct(
        public bool $success,
        public string $message,
        public ?TranslationResult $result = null,
    ) {}
}
