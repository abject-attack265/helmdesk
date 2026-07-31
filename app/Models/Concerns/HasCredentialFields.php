<?php

namespace App\Models\Concerns;

/**
 * 根据凭据字段定义校验和合并供应商凭据。
 *
 * 使用方需具备 array $credential_fields 与 ?array $credentials（encrypted:array cast）属性。
 */
trait HasCredentialFields
{
    /**
     * 判断该供应商的所有必填凭据是否都已填写。
     */
    public function hasCompleteCredentials(): bool
    {
        $credentials = $this->credentials;

        foreach ($this->credential_fields as $field) {
            if (! $field['required']) {
                continue;
            }

            if (blank($credentials[$field['field']] ?? null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 合并提交凭据，空值清除字段，未提交字段保持原值。
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function mergeCredentials(array $input): array
    {
        $merged = $this->credentials ?? [];

        foreach ($this->credential_fields as $field) {
            $fieldName = $field['field'];
            if (! array_key_exists($fieldName, $input)) {
                continue;
            }

            $value = $input[$fieldName];

            if (blank($value)) {
                unset($merged[$fieldName]);

                continue;
            }

            $merged[$fieldName] = $value;
        }

        return $merged;
    }
}
