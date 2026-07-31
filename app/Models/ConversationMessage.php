<?php

namespace App\Models;

use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Services\Translation\TranslationText;
use Database\Factories\ConversationMessageFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Scout\Searchable;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $conversation_id 所属会话
 * @property string|null $sender_user_id 发送者同事用户
 * @property string $sender_name 发送者展示名快照
 * @property MessageRole $role 发送方角色
 * @property MessageKind $kind 消息类型
 * @property string|null $content 文本内容
 * @property string|null $content_locale 内容原文语言
 * @property array|null $payload 结构化负载
 * @property float|null $confidence AI 回复置信度
 * @property string|null $reception_plan_version_id 接待方案版本快照
 * @property string|null $turn_id 接待轮次 ID
 * @property string|null $client_msg_id 客户端幂等键
 * @property int $seq_no 会话内单调序号
 * @property MessageDeliveryStatus $delivery_status 投递状态
 * @property string|null $quoted_message_id 引用消息
 * @property Carbon|null $recalled_at 撤回时间
 * @property mixed $use_factory
 * @property int|null $conversations_count
 * @property int|null $sender_users_count
 * @property int|null $attachments_count
 * @property int|null $quoted_messages_count
 * @property-read Conversation $conversation
 * @property-read User|null $senderUser
 * @property-read Collection|Attachment[] $attachments
 * @property-read ConversationMessage|null $quotedMessage
 * @property-read MessageOutbox|null $outbox
 *
 * @method static \Database\Factories\ConversationMessageFactory<self> factory($count = null, $state = [])
 */
class ConversationMessage extends Model
{
    /**
     * 会话消息模型，保存访客、AI、客服和工具调用产生的时间线消息。
     */

    /** @use HasFactory<ConversationMessageFactory> */
    use HasFactory, HasUlids, Searchable;

    protected $guarded = [];

    /**
     * 查找会话内带相同 client_msg_id 的已有消息；客户端/渠道重投同一消息时返回首次落库的行，保证幂等。
     */
    public static function findByClientMsgId(string $conversationId, string $clientMsgId): ?self
    {
        return self::query()
            ->where('conversation_id', $conversationId)
            ->where('client_msg_id', $clientMsgId)
            ->first();
    }

    /** 返回当前会话内可引用的消息 ID，并拒绝不存在、已撤回或角色不符的目标。 */
    public static function resolveQuotedMessageId(string $conversationId, ?string $quotedMessageId, ?MessageRole $role = null): ?string
    {
        if ($quotedMessageId === null) {
            return null;
        }

        $quotedMessageId = trim($quotedMessageId);
        $exists = self::query()
            ->where('conversation_id', $conversationId)
            ->whereKey($quotedMessageId)
            ->whereNull('recalled_at')
            ->when($role !== null, fn ($query) => $query->where('role', $role))
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'quoted_message_id' => __('conversation.errors.invalid_quoted_message'),
            ]);
        }

        return $quotedMessageId;
    }

    /**
     * 生成携带消息序号和客户端消息 ID 的实时事件元数据。
     *
     * @return array{message_id: string, seq_no: int, client_msg_id: ?string}
     */
    public function realtimeMeta(): array
    {
        return [
            'message_id' => (string) $this->id,
            'seq_no' => (int) $this->seq_no,
            'client_msg_id' => $this->client_msg_id,
        ];
    }

    /**
     * 判断消息是否含可用内容且允许进入 AI 会话历史。
     */
    public function isUsableAiHistoryMessage(): bool
    {
        $hasContent = trim($this->content ?? '') !== ''
            || $this->attachments->isNotEmpty();

        if (! $hasContent) {
            return false;
        }

        return true;
    }

    /**
     * 为指定会话原子分配下一个 seq_no。
     */
    public static function allocateSeqNo(string $conversationId): int
    {
        $row = DB::selectOne(
            'UPDATE conversations SET next_seq_no = next_seq_no + 1 WHERE id = ? RETURNING next_seq_no',
            [$conversationId],
        );

        if ($row === null) {
            throw new \RuntimeException("Conversation {$conversationId} not found while allocating seq_no.");
        }

        return (int) $row->next_seq_no;
    }

    /**
     * 返回会话消息字段的类型转换配置。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => MessageRole::class,
            'kind' => MessageKind::class,
            'payload' => 'array',
            'confidence' => 'float',
            'seq_no' => 'integer',
            'delivery_status' => MessageDeliveryStatus::class,
            'recalled_at' => 'datetime',
        ];
    }

    /**
     * 关联消息所属会话。
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * 关联发送消息的客服用户。
     */
    public function senderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id')->withTrashed();
    }

    /**
     * 关联消息绑定的附件。
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * 关联当前消息引用回复的目标消息。
     */
    public function quotedMessage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'quoted_message_id');
    }

    /** 关联消息的异步投递记录。 */
    public function outbox(): HasOne
    {
        return $this->hasOne(MessageOutbox::class, 'conversation_message_id');
    }

    /** 撤回时效窗口：消息创建后 N 分钟内允许撤回。 */
    public const int RECALL_WINDOW_MINUTES = 2;

    /**
     * 判断该消息是否已被撤回。
     */
    public function isRecalled(): bool
    {
        return $this->recalled_at !== null;
    }

    /** 标记消息已撤回，并在其仍为最新消息时覆盖会话预览；原始正文保留用于审计与离线投递。 */
    public function markRecalled(Conversation $conversation): void
    {
        DB::transaction(function () use ($conversation): void {
            $this->update(['recalled_at' => now()]);

            // 撤回内容立即退出全文搜索索引。
            $this->unsearchable();

            // seq_no 判断被撤回消息是否仍为会话最新消息。
            $hasNewerMessage = self::query()
                ->where('conversation_id', $conversation->id)
                ->where('seq_no', '>', $this->seq_no)
                ->exists();

            if (! $hasNewerMessage) {
                $conversation->update([
                    'last_message_preview' => __('conversation.message_recalled_placeholder'),
                ]);
            }
        });
    }

    /**
     * 判断该消息是否仍处于可撤回的时效窗口内。
     */
    public function isWithinRecallWindow(): bool
    {
        return $this->created_at->diffInSeconds(now(), absolute: true) <= self::RECALL_WINDOW_MINUTES * 60;
    }

    /**
     * 生成消息 payload 中保存的附件快照。
     *
     * @return array<string, mixed>
     */
    public static function attachmentSnapshot(Attachment $attachment): array
    {
        return [
            'id' => (string) $attachment->id,
            'name' => $attachment->original_name,
            'mime_type' => $attachment->mime_type,
            'byte_size' => $attachment->byte_size,
            'width' => $attachment->metadata['width'] ?? null,
            'height' => $attachment->metadata['height'] ?? null,
        ];
    }

    /**
     * 生成无文本附件消息的会话预览文案。
     */
    public function attachmentPreview(): string
    {
        return $this->kind === MessageKind::Image ? '[图片]' : '[文件]';
    }

    /**
     * 判断消息是否为可翻译的有效文本。
     */
    public function isEligibleForTranslation(): bool
    {
        return ! $this->isRecalled()
            && in_array($this->role, [MessageRole::Visitor, MessageRole::Ai, MessageRole::Teammate], true)
            && $this->kind === MessageKind::Text
            && TranslationText::hasTranslatableLetters((string) $this->content);
    }

    /**
     * 返回全文检索索引所需的消息字段。
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'search_text' => $this->searchableText(),
        ];
    }

    /**
     * 判断消息是否包含可索引文本。撤回后的消息不再参与全文检索。
     */
    public function shouldBeSearchable(): bool
    {
        return ! $this->isRecalled() && $this->searchableText() !== '';
    }

    /**
     * 汇总消息正文及其译文作为全文检索内容。
     */
    private function searchableText(): string
    {
        $texts = [];
        if (is_string($this->content) && $this->content !== '') {
            $texts[] = $this->content;
        }

        $translations = $this->payload['translations'] ?? [];
        if (! is_array($translations)) {
            return implode("\n", $texts);
        }

        foreach ($translations as $translation) {
            $text = is_array($translation) ? ($translation['text'] ?? null) : null;
            if (is_string($text) && $text !== '') {
                $texts[] = $text;
            }
        }

        return implode("\n", $texts);
    }
}
