<?php

namespace App\Models;

use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $contact_id
 * @property string $channel_id
 * @property string $current_conversation_id
 * @property ConversationStatus $status
 * @property ConversationInboxStatus $inbox_status
 * @property string|null $assigned_user_id
 * @property bool $is_important
 * @property Carbon $last_activity_at
 * @property int|null $contacts_count
 * @property int|null $channels_count
 * @property int|null $current_conversations_count
 * @property-read Contact $contact
 * @property-read Channel $channel
 * @property-read Conversation $currentConversation
 */
class ConversationThread extends Model
{
    /**
     * 线程以联系人和渠道组合为稳定身份，保存当前会话的筛选与排序投影。
     */
    use HasUlids;

    protected $guarded = [];

    /**
     * 返回线程投影字段的类型转换配置。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ConversationStatus::class,
            'inbox_status' => ConversationInboxStatus::class,
            'is_important' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    /**
     * 从已限定身份的查询中返回开放会话或最近关闭的会话。
     *
     * @param  Builder<Conversation>  $query
     */
    public static function representativeConversation(Builder $query): ?Conversation
    {
        $open = (clone $query)
            ->where('conversations.status', ConversationStatus::Open)
            ->first();

        return $open ?? (clone $query)
            ->where('conversations.status', ConversationStatus::Closed)
            ->orderByDesc('conversations.closed_at')
            ->orderByDesc('conversations.created_at')
            ->orderByDesc('conversations.id')
            ->first();
    }

    /**
     * 计算会话在线程列表中的活动时间。
     */
    public static function activityAt(Conversation $conversation): Carbon
    {
        if ($conversation->status === ConversationStatus::Closed) {
            return $conversation->closed_at
                ?? throw new LogicException("已关闭会话 {$conversation->id} 缺少关闭时间。");
        }

        return $conversation->lastActivityAt();
    }

    /**
     * 从代表会话生成线程当前状态投影字段。
     *
     * @return array{
     *     current_conversation_id: string,
     *     status: string,
     *     inbox_status: string,
     *     assigned_user_id: string|null,
     *     last_activity_at: Carbon
     * }
     */
    public static function projectionFromConversation(Conversation $conversation): array
    {
        return [
            'current_conversation_id' => (string) $conversation->id,
            'status' => $conversation->status->value,
            'inbox_status' => $conversation->inbox_status->value,
            'assigned_user_id' => $conversation->assigned_user_id !== null
                ? (string) $conversation->assigned_user_id
                : null,
            'last_activity_at' => self::activityAt($conversation),
        ];
    }

    /**
     * 构造联系人渠道身份对应的线程查询。
     *
     * @return Builder<ConversationThread>
     */
    public static function queryForIdentity(string $contactId, string $channelId): Builder
    {
        return self::query()
            ->where('contact_id', $contactId)
            ->where('channel_id', $channelId);
    }

    /**
     * 按数据库中的会话身份解析所属线程。
     */
    public static function findForConversation(Conversation $conversation): ?self
    {
        return self::query()
            ->join('conversations as resolved_conversation', function (JoinClause $join): void {
                $join
                    ->on('resolved_conversation.contact_id', '=', 'conversation_threads.contact_id')
                    ->on('resolved_conversation.channel_id', '=', 'conversation_threads.channel_id');
            })
            ->where('resolved_conversation.id', $conversation->id)
            ->select('conversation_threads.*')
            ->first();
    }

    /**
     * 按会话 ID 返回以其为当前代表的线程。
     */
    public static function findCurrentForConversation(Conversation $conversation): ?self
    {
        return self::query()
            ->where('current_conversation_id', $conversation->id)
            ->first();
    }

    /**
     * 返回会话所属线程，缺少完整身份或线程时直接失败。
     */
    public static function requireForConversation(Conversation $conversation): self
    {
        return self::findForConversation($conversation)
            ?? throw new LogicException("会话 {$conversation->id} 缺少收件箱线程。");
    }

    /**
     * 关联线程联系人。
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }

    /**
     * 关联线程渠道。
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class)->withTrashed();
    }

    /**
     * 关联线程当前代表会话。
     */
    public function currentConversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'current_conversation_id');
    }
}
