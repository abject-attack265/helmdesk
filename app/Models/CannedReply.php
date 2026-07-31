<?php

namespace App\Models;

use Database\Factories\CannedReplyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $user_id 归属用户；NULL 为应用共享，非 NULL 为该用户私有
 * @property string $name
 * @property string|null $shortcut 快捷输入触发词
 * @property string $content 回复正文
 * @property int $usage_count 被使用次数
 * @property Carbon|null $last_used_at 最近一次使用时间
 * @property array|null $metadata 扩展元数据：embedding、AI 生成标记等
 * @property string|null $created_by_user_id 创建者用户
 * @property string|null $updated_by_user_id 最近更新者用户
 * @property mixed $use_factory
 * @property int|null $owners_count
 * @property-read User|null $owner
 *
 * @method static \Database\Factories\CannedReplyFactory<self> factory($count = null, $state = [])
 */
class CannedReply extends Model
{
    /**
     * 快捷回复模版，承载应用共享或客服个人沉淀的可复用回复内容。
     */

    /** @use HasFactory<CannedReplyFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_used_at' => 'datetime',
            'usage_count' => 'integer',
        ];
    }

    /**
     * 个人模版的归属用户；应用共享时为 null。
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /**
     * 是否为应用共享模版（非个人）。
     */
    public function isInstanceShared(): bool
    {
        return $this->user_id === null;
    }

    /**
     * 是否归属于指定用户的个人模版。
     */
    public function isOwnedBy(User $user): bool
    {
        return $this->user_id !== null && (string) $this->user_id === (string) $user->id;
    }
}
