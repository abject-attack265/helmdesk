<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $conversation_id
 * @property-read Conversation $conversation
 * @property-read Collection|AiAssistantMessage[] $messages
 */
class AiAssistantThread extends Model
{
    /**
     * AI 助手独立对话线程，通过创建时的客户会话归入联系人历史。
     */
    use HasUlids;

    protected $guarded = [];

    /**
     * 关联线程所属客户会话。
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * 返回线程内按时间排序的问答消息。
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AiAssistantMessage::class, 'thread_id')
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * 将线程范围限定为当前客户会话所属联系人的 AI 历史；匿名会话只匹配自身。
     */
    public function scopeForContactContext(Builder $query, Conversation $conversation): Builder
    {
        if ($conversation->contact_id === null) {
            return $query->where('conversation_id', $conversation->id);
        }

        return $query->whereHas(
            'conversation',
            fn (Builder $conversationQuery): Builder => $conversationQuery
                ->where('contact_id', $conversation->contact_id),
        );
    }
}
