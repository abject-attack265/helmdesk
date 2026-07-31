<?php

namespace App\Models;

use Database\Factories\IntegrationToolFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $integration_id 所属集成，指向 integrations.id
 * @property string $name
 * @property string|null $description 工具描述，由远端返回
 * @property array|null $input_schema 工具入参的 JSON Schema 定义
 * @property array|null $annotations 工具注解元数据（如只读 / 破坏性等提示）
 * @property bool $is_enabled 是否启用，已下线工具会被强制置 false
 * @property Carbon|null $last_seen_at 最近一次同步时仍存在于远端的时间
 * @property Carbon|null $removed_at 远端不再返回该工具的时间，标记已下线
 * @property mixed $use_factory
 * @property int|null $servers_count
 * @property-read Integration $server
 *
 * @method static \Database\Factories\IntegrationToolFactory<self> factory($count = null, $state = [])
 */
class IntegrationTool extends Model
{
    /**
     * 集成下从远端拉取并缓存的工具描述。
     * 每次同步时按 (integration_id, name) 增量写入；远端不再返回的工具不物理删除，
     * 置 removed_at 并强制 is_enabled=false 显示"已下线"。
     */

    /** @use HasFactory<IntegrationToolFactory> */
    use HasFactory, HasUlids;

    protected $table = 'integration_tools';

    protected $guarded = [];

    /**
     * 工具字段类型转换：JSON 子段与时间戳。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'input_schema' => 'array',
            'annotations' => 'array',
            'is_enabled' => 'boolean',
            'last_seen_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    /**
     * 工具所属的集成。
     */
    public function server(): BelongsTo
    {
        return $this->belongsTo(Integration::class, 'integration_id');
    }
}
