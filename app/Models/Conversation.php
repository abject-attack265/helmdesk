<?php

namespace App\Models;

use App\Casts\ConversationChannelContextCast;
use App\Data\Conversation\ChannelContext\ConversationChannelContextData;
use App\Enums\ConversationEntryMode;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationSource;
use App\Enums\ConversationStatus;
use App\Enums\ConversationVisitorReplyStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use Closure;
use Database\Factories\ConversationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $contact_id 关联联系人；匿名访客可为空
 * @property string|null $assigned_user_id 当前接管的同事用户
 * @property string|null $channel_id 接入渠道
 * @property string|null $reception_plan_version_id 本会话锁定的接待方案版本快照
 * @property ConversationEntryMode|null $entry_mode 访客进入模式：standalone / widget 等
 * @property string $visitor_locale 访客界面语言
 * @property ConversationSource $source 会话来源：manual / 渠道自动创建等
 * @property ConversationStatus $status 会话状态：open / closed
 * @property ConversationInboxStatus $inbox_status 接待状态：ai_handling / teammate_pending / teammate_handling
 * @property bool $waiting_for_visitor_reply 是否在等访客回复
 * @property string|null $subject
 * @property string|null $summary AI 生成的会话摘要
 * @property string|null $summary_locale 摘要原文语言
 * @property array|null $summary_translations 摘要多语言翻译缓存
 * @property int $summary_last_message_seq_no 摘要覆盖到的最后消息序号
 * @property Carbon|null $summary_generated_at 摘要最近生成时间
 * @property array|null $ai_context AI 滚动上下文（事实点等），供联系人级摘要吸收
 * @property ConversationChannelContextData|null $channel_context 渠道上下文快照
 * @property string|null $last_message_preview 最后一条消息预览文本
 * @property Carbon|null $last_message_at 最后一条消息时间，列表排序用
 * @property int $unread_visitor_message_count 未读访客消息数
 * @property int $unread_agent_message_count 未读同事消息数
 * @property int $next_seq_no 会话内消息序号自增水位
 * @property Carbon|null $closed_at 会话关闭时间
 * @property Carbon|null $reopened_at 会话最近一次被重新打开的时间，参与空闲自动关闭判定
 * @property mixed $use_factory
 * @property int|null $channels_count
 * @property int|null $contacts_count
 * @property int|null $reception_plan_versions_count
 * @property int|null $assigned_users_count
 * @property int|null $messages_count
 * @property int|null $latest_messages_count
 * @property int|null $events_count
 * @property int|null $page_views_count
 * @property int|null $tags_count
 * @property-read Channel|null $channel
 * @property-read Contact|null $contact
 * @property-read ReceptionPlanVersion|null $receptionPlanVersion
 * @property-read User|null $assignedUser
 * @property-read Collection|ConversationMessage[] $messages
 * @property-read ConversationMessage|null $latestMessage
 * @property-read Collection|ConversationEvent[] $events
 * @property-read Collection|ConversationPageView[] $pageViews
 * @property-read Collection|Tag[] $tags
 *
 * @method static \Database\Factories\ConversationFactory<self> factory($count = null, $state = [])
 */
class Conversation extends Model
{
    /**
     * 会话模型，保存一次客户接待的完整生命周期：来源渠道、接待方案版本快照、消息历史、状态流转与 AI 上下文。
     *
     * 业务约束：同 app + channel + contact 同一时刻最多一条 status=open 的会话；
     * closed 后允许同 contact 在同 channel 上再开新会话（由 conversations_one_open_per_contact_channel partial unique index 保证）。
     */
    /** @use HasFactory<ConversationFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    /**
     * last_message_preview 的最大长度；所有写入会话预览的入口统一经 messagePreview() 截断。
     */
    public const int PREVIEW_LENGTH = 120;

    /**
     * 把消息文本截断成会话列表用的预览文案。
     */
    public static function messagePreview(string $text): string
    {
        return Str::limit($text, self::PREVIEW_LENGTH, '');
    }

    /**
     * 会话字段的类型转换。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entry_mode' => ConversationEntryMode::class,
            'source' => ConversationSource::class,
            'status' => ConversationStatus::class,
            'inbox_status' => ConversationInboxStatus::class,
            'waiting_for_visitor_reply' => 'boolean',
            'summary_translations' => 'array',
            'summary_last_message_seq_no' => 'integer',
            'summary_generated_at' => 'datetime',
            'ai_context' => 'array',
            'channel_context' => ConversationChannelContextCast::class,
            'last_message_at' => 'datetime',
            'unread_visitor_message_count' => 'integer',
            'unread_agent_message_count' => 'integer',
            'next_seq_no' => 'integer',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    /**
     * 会话最近活跃时间，供空闲自动关闭判定使用；重新打开会刷新它，让会话重获完整空闲窗口。
     */
    public function lastActivityAt(): Carbon
    {
        return collect([$this->last_message_at, $this->reopened_at])
            ->filter()
            ->max() ?? $this->created_at;
    }

    public function waitingForVisitorReplyLabel(): ?string
    {
        return $this->waiting_for_visitor_reply
            ? ConversationVisitorReplyStatus::Waiting->label()
            : null;
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class)->withTrashed();
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }

    /**
     * 会话锁定的接待方案版本快照；即便渠道后续重新部署新版本，本会话仍按创建时版本回放。
     */
    public function receptionPlanVersion(): BelongsTo
    {
        return $this->belongsTo(ReceptionPlanVersion::class, 'reception_plan_version_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id')->withTrashed();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ConversationMessage::class)->orderBy('seq_no');
    }

    /**
     * 是否曾被人工坐席处理：有指派坐席或出现过 teammate 消息。
     * 用于关单后判定是否值得从人工接待中蒸馏经验。
     */
    public function wasHumanHandled(): bool
    {
        if ($this->assigned_user_id !== null) {
            return true;
        }

        return $this->messages()
            ->where('role', MessageRole::Teammate)
            ->exists();
    }

    /**
     * 会话最后一条消息，用于列表展示当前 viewer 视角下的最后消息摘要。
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(ConversationMessage::class)->latestOfMany('seq_no');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ConversationEvent::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * 本次会话的访客浏览轨迹，按访问时间排序。
     */
    public function pageViews(): HasMany
    {
        return $this->hasMany(ConversationPageView::class)->orderBy('viewed_at')->orderBy('id');
    }

    /**
     * 本次会话的有效标签（AI 自动或人工打）；不含已被人工抑制（removed_at）的记录。
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'conversation_tag_assignments')
            ->withPivot('source', 'confidence', 'reason', 'assigned_by_user_id', 'based_on_seq_no', 'created_at')
            ->wherePivotNull('removed_at');
    }

    /**
     * withCount / loadCount 计算展示用消息数时共用的过滤器：
     * 仅统计访客、AI、坐席的非空、未撤回文本消息。
     */
    public static function displayMessageCountQuery(): Closure
    {
        return function (Builder $query): void {
            $query
                ->whereIn('role', [MessageRole::Visitor, MessageRole::Ai, MessageRole::Teammate])
                ->where('kind', MessageKind::Text)
                ->whereNotNull('content')
                ->whereNull('recalled_at');
        };
    }
}
