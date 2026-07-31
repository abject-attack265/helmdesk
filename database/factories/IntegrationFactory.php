<?php

namespace Database\Factories;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncStatus;
use App\Enums\IntegrationTransport;
use App\Models\Integration;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Integration>
 */
class IntegrationFactory extends Factory
{
    /**
     * 集成的默认状态：MCP provider、无凭据、尚未同步，便于测试控制凭据走读与同步状态。
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company().' MCP';

        return [
            'provider' => IntegrationProvider::Mcp,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'name' => $name,
            'transport' => IntegrationTransport::StreamableHttp,
            'endpoint_url' => 'https://'.fake()->domainName().'/mcp',
            'credentials' => null,
            'headers' => null,
            'timeout_seconds' => 30,
            'last_synced_at' => null,
            'last_sync_status' => IntegrationSyncStatus::Pending,
            'last_sync_error' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * 写入 Bearer Token 凭据（落库即标准 Authorization: Bearer <token> header 对）。
     */
    public function withBearerToken(string $token = 'test-token-xxx'): self
    {
        return $this->state([
            'credentials' => [
                'auth_header_name' => 'Authorization',
                'auth_header_value' => 'Bearer '.$token,
            ],
        ]);
    }

    /**
     * 写入自定义认证 header 凭据。
     */
    public function withAuthHeader(string $name, string $value): self
    {
        return $this->state([
            'credentials' => [
                'auth_header_name' => $name,
                'auth_header_value' => $value,
            ],
        ]);
    }

    /**
     * 标记一次成功同步。
     */
    public function synced(): self
    {
        return $this->state([
            'last_synced_at' => now(),
            'last_sync_status' => IntegrationSyncStatus::Success,
            'last_sync_error' => null,
        ]);
    }

    /**
     * 标记一次失败同步：运行时不应把这个集成挂给 AI Agent。
     */
    public function syncFailed(string $error = 'list-tools 调用失败'): self
    {
        return $this->state([
            'last_synced_at' => now(),
            'last_sync_status' => IntegrationSyncStatus::Failed,
            'last_sync_error' => $error,
        ]);
    }
}
