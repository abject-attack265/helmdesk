<?php

namespace App\Data\Integration;

use App\Enums\IntegrationProvider;
use App\Services\Mcp\McpEndpointGuard;
use Closure;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Validation\ValidationContext;

/**
 * 集成创建表单提交的数据。
 *
 * 认证统一为一对 header：
 *  - 都填即开启认证；
 *  - 都不填即无认证；
 *  - 只填一个会触发校验错误（不允许半配置）。
 *
 * 访问令牌由前端转换为 `Authorization: Bearer <token>` 请求头后提交。
 *
 * 用户选择集成类型，传输协议由集成类型决定。
 */
class FormCreateIntegrationData extends Data
{
    public function __construct(
        public string $name,
        public string $endpoint_url,
        public IntegrationProvider $provider,
        public ?string $auth_header_name = null,
        public ?string $auth_header_value = null,
        /** @var array<string, string>|null */
        public ?array $headers = null,
        public ?int $timeout_seconds = null,
    ) {}

    /**
     * 表单校验规则：endpoint 必填 http(s) URL，认证 header name/value 不能只填一个。
     *
     * `nullable` 会让 Laravel 跳过 `required_with`，故这里根据当前请求的填值情况
     * 动态决定哪一边变成强制 `required`，从而既允许"都空"，也阻止"只填一边"。
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(ValidationContext $context): array
    {
        /** @var array<string, mixed> $payload */
        $payload = is_array($context->payload) ? $context->payload : [];
        $hasName = filled($payload['auth_header_name'] ?? null);
        $hasValue = filled($payload['auth_header_value'] ?? null);

        // 集成类型与创建页下拉选项同源，排除只用于开发演示的类型。
        $selectableProviders = array_map(
            static fn (IntegrationProvider $provider): string => $provider->value,
            IntegrationProvider::userSelectableCases(),
        );

        $rules = [
            'name' => ['required', 'string', 'max:128'],
            'endpoint_url' => ['required', 'string', 'url:http,https', 'max:2048', self::safeEndpointRule()],
            'provider' => ['required', Rule::in($selectableProviders)],
            'auth_header_name' => ['nullable', 'string', 'max:128'],
            'auth_header_value' => ['nullable', 'string', 'max:4096'],
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
