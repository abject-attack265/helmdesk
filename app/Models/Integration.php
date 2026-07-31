<?php

namespace App\Models;

use App\Enums\IntegrationProvider;
use App\Enums\IntegrationSyncStatus;
use App\Enums\IntegrationTransport;
use Database\Factories\IntegrationFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property IntegrationProvider $provider 集成类型
 * @property string $slug
 * @property string $name
 * @property IntegrationTransport $transport 集成使用的传输协议
 * @property string $endpoint_url 外部服务地址
 * @property array|null $credentials 加密存储的验证信息
 * @property array|null $headers 用户自定义请求头键值对，随每次调用一起发送
 * @property int $timeout_seconds 单次调用超时时间（秒）
 * @property Carbon|null $last_synced_at 最近一次同步工具列表的时间
 * @property IntegrationSyncStatus $last_sync_status 最近一次同步结果：pending / syncing / success / failed
 * @property string|null $last_sync_error 最近一次同步失败的错误信息
 * @property int $sort_order 列表展示排序，值越小越靠前
 * @property mixed $use_factory
 * @property int|null $tools_count
 * @property-read Collection|IntegrationTool[] $tools
 *
 * @method static \Database\Factories\IntegrationFactory<self> factory($count = null, $state = [])
 */
class Integration extends Model
{
    /**
     * 系统级集成连接。
     *
     * 保存外部服务地址、验证信息、自定义请求头、工具更新状态和工具记录。
     */

    /** @use HasFactory<IntegrationFactory> */
    use HasFactory, HasUlids;

    protected $table = 'integrations';

    protected $guarded = [];

    /**
     * 集成字段的类型转换：provider / 传输枚举、加密 JSON、时间戳。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => IntegrationProvider::class,
            'transport' => IntegrationTransport::class,
            'credentials' => 'encrypted:array',
            'headers' => 'array',
            'timeout_seconds' => 'integer',
            'last_synced_at' => 'datetime',
            'last_sync_status' => IntegrationSyncStatus::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * 集成最近一次同步缓存下来的工具列表（含已下线）。
     */
    public function tools(): HasMany
    {
        return $this->hasMany(IntegrationTool::class);
    }

    /**
     * 请求头名称和值均非空时生成验证信息。
     *
     * @return array<string, string>|null
     */
    public static function buildAuthCredentials(?string $name, ?string $value): ?array
    {
        $name = trim($name ?? '');
        $value = trim($value ?? '');

        if ($name === '' || $value === '') {
            return null;
        }

        return [
            'auth_header_name' => $name,
            'auth_header_value' => $value,
        ];
    }

    /**
     * 归一化自定义请求头：丢弃非字符串键和非标量值，空值剔除。
     *
     * @param  array<string, mixed>|null  $headers
     * @return array<string, string>|null
     */
    public static function normalizeHeaders(?array $headers): ?array
    {
        if ($headers === null) {
            return null;
        }

        $normalized = [];
        foreach ($headers as $key => $value) {
            if (! is_string($key) || ! is_scalar($value)) {
                continue;
            }
            $stringValue = trim((string) $value);
            if ($stringValue === '') {
                continue;
            }
            $normalized[$key] = $stringValue;
        }

        return $normalized === [] ? null : $normalized;
    }
}
