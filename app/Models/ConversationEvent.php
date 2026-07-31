<?php

namespace App\Models;

use App\Enums\ConversationEventType;
use Database\Factories\ConversationEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property string $conversation_id 所属会话
 * @property string|null $actor_user_id 触发事件的同事用户；系统事件为空
 * @property ConversationEventType $type 事件类型：created / assignment_changed / handoff_requested / status_changed / reception_turn_started / reception_tool_called / reception_turn_ended / feedback_received
 * @property array|null $payload 事件结构化负载（变更前后值、工具调用详情等）
 * @property mixed $use_factory
 * @property int|null $conversations_count
 * @property int|null $actor_users_count
 * @property-read Conversation $conversation
 * @property-read User|null $actorUser
 *
 * @method static \Database\Factories\ConversationEventFactory<self> factory($count = null, $state = [])
 */
class ConversationEvent extends Model
{
    /**
     * 会话事件模型，记录转人工、关闭、重开等系统时间线事件。
     */

    /** @use HasFactory<ConversationEventFactory> */
    use HasFactory, HasUlids;

    public const ?string UPDATED_AT = null;

    protected $guarded = [];

    /**
     * 返回会话事件字段的类型转换配置。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ConversationEventType::class,
            'payload' => 'array',
        ];
    }

    /**
     * 关联事件所属会话。
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * 关联触发事件的客服用户。
     */
    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id')->withTrashed();
    }
}
