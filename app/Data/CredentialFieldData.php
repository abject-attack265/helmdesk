<?php

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * AI 与翻译供应商动态凭据表单的字段定义。
 */
class CredentialFieldData extends Data
{
    /**
     * 创建可序列化到前端并写入供应商快照的字段定义。
     */
    public function __construct(
        public string $field,
        public string $label,
        public string $type,
        public bool $required,
        public bool $secret,
        public ?string $default,
        public ?string $placeholder,
    ) {}
}
