<?php

namespace App\Data\Integration;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncStatus;
use App\Enums\IntegrationTransport;
use App\Models\Integration;
use LogicException;
use Spatie\LaravelData\Data;

/**
 * 集成列表与编辑表单使用的详情数据。
 *
 * 验证请求头明文下发供编辑表单回显，相关页面仅系统所有者可访问。
 */
class IntegrationData extends Data
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public IntegrationProvider $provider,
        public string $provider_label,
        public IntegrationTransport $transport,
        public string $transport_label,
        public string $endpoint_url,
        public ?string $auth_header_name,
        public ?string $auth_header_value,
        public string $auth_method_label,
        /** @var array<string, string>|null */
        public ?array $headers,
        public int $timeout_seconds,
        public ?string $last_synced_at,
        public IntegrationSyncStatus $last_sync_status,
        public string $last_sync_status_label,
        public ?string $last_sync_error,
        public int $tools_count,
        public int $removed_tools_count,
        public int $sort_order,
        /** @var IntegrationToolData[] */
        public array $tools,
    ) {}

    /**
     * 从已加载工具关系的模型装配展示数据。
     */
    public static function fromModel(Integration $integration): self
    {
        if (! $integration->relationLoaded('tools')) {
            throw new LogicException('Integration tools relation must be loaded.');
        }

        $tools = [];
        $toolsCount = 0;
        $removedCount = 0;

        foreach ($integration->tools as $tool) {
            $tools[] = IntegrationToolData::fromModel($tool);
            $toolsCount++;
            if ($tool->removed_at !== null) {
                $removedCount++;
            }
        }

        $credentials = $integration->credentials ?? [];
        $authHeaderName = isset($credentials['auth_header_name']) && is_string($credentials['auth_header_name'])
            ? $credentials['auth_header_name']
            : null;
        $authHeaderValue = isset($credentials['auth_header_value'])
            && is_string($credentials['auth_header_value'])
            && $credentials['auth_header_value'] !== ''
            ? $credentials['auth_header_value']
            : null;
        $hasAuthCredentials = $authHeaderName !== null && $authHeaderValue !== null;

        return new self(
            id: (string) $integration->id,
            slug: (string) $integration->slug,
            name: (string) $integration->name,
            provider: $integration->provider,
            provider_label: $integration->provider->label(),
            transport: $integration->transport,
            transport_label: $integration->transport->label(),
            endpoint_url: (string) $integration->endpoint_url,
            auth_header_name: $authHeaderName,
            auth_header_value: $authHeaderValue,
            auth_method_label: self::authMethodLabel($hasAuthCredentials, $authHeaderName),
            headers: $integration->headers,
            timeout_seconds: (int) $integration->timeout_seconds,
            last_synced_at: $integration->last_synced_at?->toIso8601String(),
            last_sync_status: $integration->last_sync_status,
            last_sync_status_label: $integration->last_sync_status->label(),
            last_sync_error: $integration->last_sync_error,
            tools_count: $toolsCount,
            removed_tools_count: $removedCount,
            sort_order: (int) $integration->sort_order,
            tools: $tools,
        );
    }

    /**
     * 生成认证方式展示文案。
     */
    private static function authMethodLabel(bool $hasAuthCredentials, ?string $authHeaderName): string
    {
        if (! $hasAuthCredentials) {
            return __('integration.auth_presets.none');
        }

        if ($authHeaderName !== null && strtolower($authHeaderName) === 'authorization') {
            return __('integration.auth_presets.bearer');
        }

        return $authHeaderName ?: __('integration.auth_presets.header');
    }
}
