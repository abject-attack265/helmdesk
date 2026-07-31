<?php

namespace App\Actions\Conversation;

use App\Data\Conversation\ConversationTimelineMessageMapData;
use App\Data\Conversation\QuotedMessageData;
use App\Enums\ConversationTimelineEntryType;
use App\Enums\MessageKind;
use App\Models\Attachment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 批量解析一页时间线的消息 payload 与引用消息。
 */
class BuildConversationTimelineMessageMapAction
{
    use AsAction;

    /**
     * 合并消息与引用消息的附件 ID，并通过最多一次附件查询构造展示映射。
     *
     * @param  Collection<int, object>  $rows
     */
    public function handle(Collection $rows): ConversationTimelineMessageMapData
    {
        $messageRows = $rows
            ->filter(fn (object $row): bool => $row->type === ConversationTimelineEntryType::Message->value)
            ->values();
        $quotedRows = $this->loadQuotedRows($messageRows);
        $rawPayloads = $messageRows
            ->concat($quotedRows->values())
            ->mapWithKeys(fn (object $row): array => [
                (string) $row->id => $row->recalled_at === null ? $row->payload : null,
            ])
            ->all();
        $resolvedPayloads = $this->resolvePayloads($rawPayloads);

        return new ConversationTimelineMessageMapData(
            message_payloads: $messageRows
                ->mapWithKeys(fn (object $row): array => [
                    (string) $row->id => $resolvedPayloads[(string) $row->id],
                ])
                ->all(),
            quoted_messages: $quotedRows
                ->mapWithKeys(fn (object $row): array => [
                    (string) $row->id => $this->buildQuotedMessage(
                        $row,
                        $resolvedPayloads[(string) $row->id],
                    ),
                ])
                ->all(),
        );
    }

    /**
     * 批量加载当前页面引用的消息。
     *
     * @param  Collection<int, object>  $messageRows
     * @return Collection<string, object>
     */
    private function loadQuotedRows(Collection $messageRows): Collection
    {
        $quotedIds = $messageRows
            ->pluck('quoted_message_id')
            ->filter()
            ->unique()
            ->values();

        if ($quotedIds->isEmpty()) {
            return collect();
        }

        $quotedRows = DB::table('conversation_messages')
            ->select('id', 'conversation_id', 'role', 'kind', 'content', 'payload', 'recalled_at', 'sender_name')
            ->whereIn('id', $quotedIds->all())
            ->get()
            ->keyBy(fn (object $row): string => (string) $row->id);

        $missingIds = $quotedIds->diff($quotedRows->keys())->values();

        if ($missingIds->isNotEmpty()) {
            Log::warning('时间线引用消息缺失', [
                'quoted_message_ids' => $missingIds->all(),
            ]);

            throw new LogicException('时间线引用的消息不存在。');
        }

        $crossConversationReferences = $messageRows
            ->filter(fn (object $row): bool => $row->quoted_message_id !== null)
            ->map(function (object $row) use ($quotedRows): ?array {
                $quoted = $quotedRows->get((string) $row->quoted_message_id);

                if ((string) $row->conversation_id === (string) $quoted->conversation_id) {
                    return null;
                }

                return [
                    'message_id' => (string) $row->id,
                    'conversation_id' => (string) $row->conversation_id,
                    'quoted_message_id' => (string) $quoted->id,
                    'quoted_conversation_id' => (string) $quoted->conversation_id,
                ];
            })
            ->filter()
            ->values();

        if ($crossConversationReferences->isNotEmpty()) {
            Log::warning('时间线消息存在跨会话引用', [
                'references' => $crossConversationReferences->all(),
            ]);

            throw new LogicException('时间线消息不能引用其他会话的消息。');
        }

        return $quotedRows;
    }

    /**
     * 解码全部 payload，并批量补充附件地址。
     *
     * @param  array<string, mixed>  $rawPayloads
     * @return array<string, array<string, mixed>|null>
     */
    private function resolvePayloads(array $rawPayloads): array
    {
        $payloads = collect($rawPayloads)
            ->map(fn (mixed $payload): ?array => $this->decodePayload($payload));
        $attachmentIds = $payloads
            ->flatMap(function (?array $payload): array {
                $items = $payload['attachments'] ?? null;

                if (! is_array($items)) {
                    return [];
                }

                return collect($items)
                    ->pluck('id')
                    ->filter()
                    ->map(fn (mixed $id): string => (string) $id)
                    ->all();
            })
            ->unique()
            ->values();

        $attachmentUrls = $attachmentIds->isEmpty()
            ? collect()
            : Attachment::query()
                ->select('id', 'storage_profile_id', 'object_key')
                ->whereIn('id', $attachmentIds->all())
                ->with('storageProfile')
                ->get()
                ->mapWithKeys(fn (Attachment $attachment): array => [
                    (string) $attachment->id => $attachment->full_url,
                ]);

        return $payloads
            ->map(fn (?array $payload): ?array => $this->enrichPayload($payload, $attachmentUrls))
            ->all();
    }

    /**
     * 将数据库 payload 解码为数组。
     *
     * @return array<string, mixed>|null
     */
    private function decodePayload(mixed $payload): ?array
    {
        if ($payload === null) {
            return null;
        }

        if (! is_string($payload) || ! str_starts_with(ltrim($payload), '{')) {
            throw new InvalidArgumentException('会话消息 payload 必须是 JSON 对象或 null。');
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($payload, true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * 为可解析的附件补充地址，并保留原始附件快照。
     *
     * @param  array<string, mixed>|null  $payload
     * @param  Collection<string, string>  $attachmentUrls
     * @return array<string, mixed>|null
     */
    private function enrichPayload(?array $payload, Collection $attachmentUrls): ?array
    {
        if ($payload === null || ! is_array($payload['attachments'] ?? null)) {
            return $payload;
        }

        $payload['attachments'] = collect($payload['attachments'])
            ->map(function (mixed $item) use ($attachmentUrls): mixed {
                if (! is_array($item) || ! isset($item['id'])) {
                    return $item;
                }

                $url = $attachmentUrls->get((string) $item['id']);

                return $url === null ? $item : array_merge($item, ['url' => $url]);
            })
            ->values()
            ->all();

        return $payload;
    }

    /**
     * 构造引用消息展示快照。
     *
     * @param  array<string, mixed>|null  $payload
     */
    private function buildQuotedMessage(object $row, ?array $payload): QuotedMessageData
    {
        $attachments = $payload['attachments'] ?? null;

        return new QuotedMessageData(
            id: (string) $row->id,
            role: (string) $row->role,
            kind: (string) $row->kind,
            sender_name: (string) $row->sender_name,
            preview: $this->buildPreview($row),
            content: $row->recalled_at === null && is_string($row->content) ? $row->content : null,
            attachments: $row->recalled_at === null && is_array($attachments) ? array_values($attachments) : [],
            recalled_at: $row->recalled_at !== null
                ? Carbon::parse((string) $row->recalled_at)->toIso8601String()
                : null,
        );
    }

    /**
     * 生成引用消息的单行预览。
     */
    private function buildPreview(object $row): string
    {
        if ($row->recalled_at !== null) {
            return __('conversation.message_recalled_placeholder');
        }

        if (is_string($row->content) && trim($row->content) !== '') {
            return str($row->content)->squish()->limit(120, '')->toString();
        }

        return match (MessageKind::from((string) $row->kind)) {
            MessageKind::Image => __('conversation.message_kinds.image'),
            MessageKind::File => __('conversation.message_kinds.file'),
            default => __('conversation.empty_content'),
        };
    }
}
