<?php

namespace App\Services\Concerns;

use App\Data\CredentialFieldData;

/**
 * 构造 AI 与翻译供应商共用的凭据表单字段定义。
 */
trait BuildsCredentialFieldDefinitions
{
    /**
     * 声明密钥字段。
     *
     * @return array<string, mixed>
     */
    private function passwordField(string $field, string $label, bool $required = true, ?string $default = null): array
    {
        return (new CredentialFieldData($field, $label, 'password', $required, true, $default, null))->toArray();
    }

    /**
     * 声明 URL 字段。
     *
     * @return array<string, mixed>
     */
    private function urlField(
        string $field,
        string $label,
        bool $required = true,
        ?string $default = null,
        ?string $placeholder = null,
    ): array {
        return (new CredentialFieldData($field, $label, 'url', $required, false, $default, $placeholder))->toArray();
    }
}
