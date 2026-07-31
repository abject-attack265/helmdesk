<?php

namespace App\Models;

use App\Enums\ConversationTimelineEntryType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $contact_id 关联联系人；匿名访客可为空
 * @property string $conversation_id 所属会话
 * @property ConversationTimelineEntryType $entry_type 条目类型：message / event
 * @property string $entry_id 指向的消息或事件 ID，与 entry_type 配合定位源记录
 * @property Carbon $occurred_at 条目发生时间，时间轴排序用
 */
class ConversationTimelineEntry extends Model
{
    /**
     * 会话时间线索引模型，只保存排序、归属和事实表指针。
     */
    use HasUlids;

    protected $guarded = [];

    /**
     * 返回时间线索引字段的类型转换配置。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_type' => ConversationTimelineEntryType::class,
            'occurred_at' => 'datetime',
        ];
    }
}
