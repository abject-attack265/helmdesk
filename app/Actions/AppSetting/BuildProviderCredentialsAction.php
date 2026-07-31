<?php

namespace App\Actions\AppSetting;

use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 按供应商凭据字段定义提取有值的配置。
 */
class BuildProviderCredentialsAction
{
    use AsAction;

    /**
     * 仅返回字段定义中声明且提交值非空的凭据。
     *
     * @param  list<array{field: string}>  $fields
     * @param  array<string, mixed>  $configuration
     * @return array<string, mixed>
     */
    public function handle(array $fields, array $configuration): array
    {
        $credentials = [];

        foreach ($fields as $field) {
            $fieldName = $field['field'];
            $value = $configuration[$fieldName] ?? null;

            if (filled($value)) {
                $credentials[$fieldName] = $value;
            }
        }

        return $credentials;
    }
}
