<?php

namespace App\Models;

use App\Enums\ChannelType;
use App\Enums\ConversationRatingHandledBy;
use App\Enums\ConversationRatingScore;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $conversation_id 被评价会话，指向 conversations.id；每会话唯一
 * @property string|null $contact_id 评价访客，指向 contacts.id
 * @property ConversationRatingScore $score 评分：positive / negative（ConversationRatingScore）
 * @property string|null $comment 选填文字反馈
 * @property ChannelType $channel_type 评价时渠道类型快照，便于分渠道统计
 * @property ConversationRatingHandledBy $handled_by 处理归属快照：ai / human，用于拆分 AI 与人工满意度
 * @property string|null $assigned_user_id 处理坐席快照（人工时），用于每坐席 CSAT
 * @property Carbon $rated_at 评价提交时间
 * @property int|null $conversations_count
 * @property int|null $assigned_users_count
 * @property-read Conversation $conversation
 * @property-read User|null $assignedUser
 */
class ConversationRating extends Model
{
    /**
     * 访客对会话的满意度评价（CSAT）。每会话唯一，可覆盖更新；
     * 提交时快照 channel_type / handled_by / assigned_user_id，供后续按渠道、AI vs 人工、坐席维度统计。
     */
    use HasUlids;

    protected $table = 'conversation_ratings';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'score' => ConversationRatingScore::class,
            'channel_type' => ChannelType::class,
            'handled_by' => ConversationRatingHandledBy::class,
            'rated_at' => 'datetime',
        ];
    }

    /**
     * 被评价的会话。
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * 处理该会话的坐席（人工处理时）。
     */
    public function assignedUser(): BelongsTo
    {
        // 坐席快照：人工坐席被软删后仍需展示其评分归属，与会话/消息/事件上的同类快照关系保持一致。
        return $this->belongsTo(User::class, 'assigned_user_id')->withTrashed();
    }
}
