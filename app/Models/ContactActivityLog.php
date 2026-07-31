<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property string $contact_id 日志所属联系人 contacts.id
 * @property string|null $actor_user_id 操作人 user_id：系统/AI 行为时为空
 * @property string $action 动作类型：created / updated / deleted / restored / identity_added / merged_into_current / tag_attached / important_marked 等
 * @property array|null $payload 动作附加数据：记录变更详情等结构化上下文
 * @property int|null $contacts_count
 * @property int|null $actors_count
 * @property-read Contact $contact
 * @property-read User|null $actor
 */
class ContactActivityLog extends Model
{
    /**
     * 联系人活动日志模型，记录创建、更新、合并、恢复和标签变更等操作历史。
     */
    use HasUlids;

    public const string ACTION_DELETED = 'deleted';

    public const string ACTION_RESTORED = 'restored';

    public const string ACTION_CREATED = 'created';

    public const string ACTION_UPDATED = 'updated';

    public const string ACTION_IDENTITY_ADDED = 'identity_added';

    public const string ACTION_IDENTITY_REPLACED = 'identity_replaced';

    public const string ACTION_IDENTITY_DELETED = 'identity_deleted';

    public const string ACTION_MERGED_INTO_CURRENT = 'merged_into_current';

    public const string ACTION_MERGED_INTO_OTHER = 'merged_into_other';

    public const string ACTION_TAG_ATTACHED = 'tag_attached';

    public const string ACTION_TAG_DETACHED = 'tag_detached';

    public const string ACTION_IMPORTANT_MARKED = 'important_marked';

    public const string ACTION_IMPORTANT_UNMARKED = 'important_unmarked';

    protected $table = 'contact_activity_logs';

    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    /**
     * 日志所属联系人。
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * 执行操作的系统成员。
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }
}
