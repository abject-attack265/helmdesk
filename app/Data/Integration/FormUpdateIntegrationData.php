<?php

namespace App\Data\Integration;

use App\Services\Mcp\McpEndpointGuard;
use Closure;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * 集成编辑表单提交的数据。
 *
 * 验证请求头由当前表单完整提交：
 *  - auth_header_name/value 都为非空字符串：覆盖当前凭据；
 *  - 都为空：清空验证信息；
 *  - 只填其中一个会触发校验错误。
 */
class FormUpdateIntegrationData extends Data
{
    public function __construct(
        public string $name,
        public string $endpoint_url,
        public ?string $auth_header_name,
        public ?string $auth_header_value,
        /** @var array<string, string>|null */
        public ?array $headers = null,
        public ?int $timeout_seconds = null,
    ) {}

    /**
     * 更新表单校验规则。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        /** @var array<string, mixed> $payload */
        $payload = is_array($context->payload) ? $context->payload : [];
        $hasName = filled($payload['auth_header_name'] ?? null);
        $hasValue = filled($payload['auth_header_value'] ?? null);

        $rules = [
            'name' => ['required', 'string', 'max:128'],
            'endpoint_url' => ['required', 'string', 'url:http,https', 'max:2048', self::safeEndpointRule()],
            'auth_header_name' => ['present', 'nullable', 'string', 'max:128'],
            'auth_header_value' => ['present', 'nullable', 'string', 'max:4096'],
            'headers' => ['nullable', 'array'],
            'headers.*' => ['string', 'max:4096'],
            'timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:120'],
        ];

        if ($hasValue && ! $hasName) {
            $rules['auth_header_name'] = ['required', 'string', 'max:128'];
        }
        if ($hasName && ! $hasValue) {
            $rules['auth_header_value'] = ['required', 'string', 'max:4096'];
        }

        return $rules;
    }

    /**
     * 拒绝指向内网或保留地址的服务地址。
     */
    private static function safeEndpointRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (is_string($value) && $value !== '' && ! McpEndpointGuard::isSafe($value)) {
                $fail(__('integration.runtime.validate.unsafe_endpoint'));
            }
        };
    }
}
