<?php

namespace App\Models;

use App\Enums\AiAssistantMessageRole;
use App\Enums\AiAssistantMessageStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $thread_id
 * @property string $round_id
 * @property AiAssistantMessageRole $role
 * @property string|null $content
 * @property array|null $segments
 * @property array|null $attachment_ids
 * @property string|null $sender_user_id
 * @property AiAssistantMessageStatus $status
 * @property-read AiAssistantThread $thread
 * @property-read User|null $senderUser
 */
class AiAssistantMessage extends Model
{
    /**
     * AI 助手线程消息，记录客服提问作者和助手回答生成状态。
     */
    use HasUlids;

    protected $guarded = [];

    /**
     * 返回消息字段的类型转换配置。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => AiAssistantMessageRole::class,
            'segments' => 'array',
            'attachment_ids' => 'array',
            'status' => AiAssistantMessageStatus::class,
        ];
    }

    /**
     * 关联消息所属 AI 助手线程。
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(AiAssistantThread::class, 'thread_id');
    }

    /**
     * 关联发起提问的客服用户。
     */
    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id')->withTrashed();
    }
}
