<?php

namespace App\Actions\Conversation;

use App\Data\Conversation\ConversationContactSummaryData;
use App\Data\Conversation\ConversationDetailData;
use App\Data\Conversation\ConversationReceptionPlanVersionSummaryData;
use App\Data\Conversation\ConversationSummaryData;
use App\Data\Conversation\ConversationTimelineData;
use App\Data\Conversation\TimelineEntryData;
use App\Data\CurrentUserContextData;
use App\Data\User\UserOptionData;
use App\Enums\ConversationEventType;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 加载会话详情页所需的消息、事件和联系人摘要。
 */
class ShowConversationDetailAction
{
    use AsAction;

    /**
     * 返回会话详情，包括联系人摘要、接待方案版本信息、指派队友和时间线。
     */
    public function handle(Conversation $conversation, ?string $cursor = null, int $perPage = 50, ?User $viewer = null): ConversationDetailData
    {
        $conversation->loadMissing(['contact', 'receptionPlanVersion.plan', 'assignedUser', 'channel']);
        $conversation->loadCount(['messages as display_message_count' => Conversation::displayMessageCountQuery()]);

        return new ConversationDetailData(
            conversation: ConversationSummaryData::fromModel($conversation),
            contact_summary: $conversation->contact ? ConversationContactSummaryData::fromModel($conversation->contact) : null,
            reception_plan_version_summary: ConversationReceptionPlanVersionSummaryData::fromModelOrNull($conversation->receptionPlanVersion),
            assigned_teammate: $conversation->assignedUser ? UserOptionData::fromModel($conversation->assignedUser) : null,
            timeline: $this->buildTimeline($conversation, $cursor, $perPage, $viewer),
        );
    }

    /**
     * 接收会话详情请求并返回 JSON 数据。
     */
    public function asController(Request $request, string $id): JsonResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $conversation = Conversation::query()->findOrFail($id);
        $viewer = User::query()->find($ctx->user_id);
        $validated = $request->validate([
            'cursor' => ['nullable', 'string'],
        ]);

        return response()->json(
            $this->handle(
                $conversation,
                $validated['cursor'] ?? null,
                viewer: $viewer,
            )->toArray()
        );
    }

    /**
     * 合并查询消息和事件，组装倒序游标分页的时间线。
     */
    private function buildTimeline(Conversation $conversation, ?string $cursor, int $perPage, ?User $viewer = null): ConversationTimelineData
    {
        $perPage = max(1, min($perPage, 100));
        $decodedCursor = $this->decodeCursor($cursor);

        $messages = DB::table('conversation_messages')
            ->selectRaw("
                conversation_messages.id,
                conversation_messages.conversation_id,
                conversation_messages.created_at as occurred_at,
                'message' as type,
                conversation_messages.role,
                conversation_messages.kind,
                null as event_type,
                conversation_messages.sender_user_id as actor_user_id,
                COALESCE(NULLIF(conversation_messages.sender_name, ''), sender_users.name) as sender_name,
                sender_users.avatar as sender_avatar_url,
                conversation_messages.content,
                conversation_messages.content_locale,
                conversation_messages.payload,
                conversation_messages.seq_no,
                conversation_messages.delivery_status,
                conversation_messages.quoted_message_id,
                conversation_messages.recalled_at,
                conversation_messages.turn_id
            ")
            ->leftJoin('users as sender_users', 'sender_users.id', '=', 'conversation_messages.sender_user_id')
            ->where('conversation_messages.conversation_id', $conversation->id);

        $events = DB::table('conversation_events')
            ->selectRaw("
                id,
                conversation_id,
                created_at as occurred_at,
                'event' as type,
                null as role,
                null as kind,
                type as event_type,
                actor_user_id,
                null as sender_name,
                null as sender_avatar_url,
                null as content,
                null as content_locale,
                payload,
                null as seq_no,
                null as delivery_status,
                null as quoted_message_id,
                null as recalled_at,
                null as turn_id
            ")
            ->where('conversation_id', $conversation->id);

        ApplyConversationTimelineEventDisplayScopeAction::run($events);

        $timelineQuery = DB::query()
            ->fromSub($messages->unionAll($events), 'timeline_entries');

        if ($decodedCursor !== null) {
            $timelineQuery->where(function ($query) use ($decodedCursor) {
                $query
                    ->where('occurred_at', '<', $decodedCursor['occurred_at'])
                    ->orWhere(function ($innerQuery) use ($decodedCursor) {
                        $innerQuery
                            ->where('occurred_at', $decodedCursor['occurred_at'])
                            ->where('id', '<', $decodedCursor['id']);
                    });
            });
        }

        $rows = $timelineQuery
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($perPage + 1)
            ->get();

        $hasMore = $rows->count() > $perPage;
        $pageRows = $hasMore ? $rows->take($perPage) : $rows;
        $messageMap = BuildConversationTimelineMessageMapAction::run($pageRows);
        $userNamesById = BuildConversationTimelineUserMapAction::run($pageRows);
        $nextCursor = $hasMore ? $this->encodeCursor(
            (string) $pageRows->last()->occurred_at,
            (string) $pageRows->last()->id,
        ) : null;

        $items = $pageRows
            ->reverse()
            ->values()
            ->map(function ($row) use ($viewer, $messageMap, $userNamesById): TimelineEntryData {
                $isMessage = $row->type === 'message';
                $isRecalled = $isMessage && $row->recalled_at !== null;
                $eventDisplay = $isMessage ? null : BuildConversationEventDisplayAction::run($row, $userNamesById);

                return new TimelineEntryData(
                    id: (string) $row->id,
                    type: (string) $row->type,
                    subtype: $this->buildSubtype($row),
                    subtype_label: $this->buildSubtypeLabel($row),
                    role: $isMessage && $row->role !== null ? (string) $row->role : null,
                    kind: $isMessage && $row->kind !== null ? (string) $row->kind : null,
                    event_type: $row->type === 'event' && $row->event_type !== null ? (string) $row->event_type : null,
                    actor_user_id: $row->actor_user_id ? (string) $row->actor_user_id : null,
                    sender_name: $isMessage && $row->sender_name !== null ? (string) $row->sender_name : null,
                    sender_avatar_url: $row->sender_avatar_url ? (string) $row->sender_avatar_url : null,
                    content: ! $isRecalled && $row->content !== null ? (string) $row->content : null,
                    content_locale: ! $isRecalled && $row->content_locale !== null ? (string) $row->content_locale : null,
                    payload: $isMessage && ! $isRecalled
                        ? $messageMap->message_payloads[(string) $row->id]
                        : null,
                    occurred_at: Carbon::parse((string) $row->occurred_at)->toIso8601String(),
                    seq_no: $isMessage && $row->seq_no !== null ? (int) $row->seq_no : null,
                    delivery_status: $isMessage && $row->delivery_status !== null ? (string) $row->delivery_status : null,
                    quoted_message_id: $isMessage && $row->quoted_message_id !== null ? (string) $row->quoted_message_id : null,
                    quoted_message: $isMessage && $row->quoted_message_id !== null
                        ? $messageMap->quoted_messages[(string) $row->quoted_message_id]
                        : null,
                    recalled_at: $isMessage && $row->recalled_at !== null ? Carbon::parse((string) $row->recalled_at)->toIso8601String() : null,
                    recalled_content: ResolveRecalledMessageContentAction::run($row, $isRecalled, $viewer),
                    event_display: $eventDisplay,
                    turn_id: $isMessage && $row->turn_id !== null ? (string) $row->turn_id : null,
                );
            })
            ->values()
            ->all();

        return new ConversationTimelineData(
            items: $items,
            next_cursor: $nextCursor,
        );
    }

    /**
     * 将 Base64 JSON 游标解码为时间线翻页位置。
     *
     * @return array{occurred_at: string, id: string}|null
     */
    private function decodeCursor(?string $cursor): ?array
    {
        if ($cursor === null) {
            return null;
        }

        $json = base64_decode($cursor, true);
        $payload = $json !== false ? json_decode($json, true) : null;

        if (
            ! is_array($payload)
            || count($payload) !== 2
            || array_diff_key($payload, ['occurred_at' => true, 'id' => true]) !== []
            || ! is_string($payload['occurred_at'])
            || ! $this->isValidCursorTimestamp($payload['occurred_at'])
            || ! is_string($payload['id'])
            || ! Str::isUlid($payload['id'])
        ) {
            throw ValidationException::withMessages([
                'cursor' => __('conversation.errors.invalid_cursor'),
            ]);
        }

        return [
            'occurred_at' => $payload['occurred_at'],
            'id' => $payload['id'],
        ];
    }

    /**
     * 将最后一条时间线记录的 occurred_at 和 id 编码为下页游标。
     */
    private function encodeCursor(string $occurredAt, string $id): string
    {
        return base64_encode(json_encode([
            'occurred_at' => Carbon::parse($occurredAt)->format('Y-m-d H:i:s'),
            'id' => $id,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * 校验 SQLite 时间线游标格式。
     */
    private function isValidCursorTimestamp(string $occurredAt): bool
    {
        if (preg_match('/^(?!0000-)\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $occurredAt) !== 1) {
            return false;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $occurredAt);
        $errors = DateTimeImmutable::getLastErrors();

        return $parsed !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }

    /**
     * 生成区分消息和事件样式的子类型标识。
     */
    private function buildSubtype(object $row): string
    {
        if ($row->type === 'message') {
            return 'message:'.(string) $row->role.':'.(string) $row->kind;
        }

        return 'event:'.(string) $row->event_type;
    }

    /**
     * 生成人类可读的消息角色/类型或事件类型标签。
     */
    private function buildSubtypeLabel(object $row): string
    {
        if ($row->type === 'message') {
            return sprintf(
                '%s · %s',
                MessageRole::from((string) $row->role)->label(),
                MessageKind::from((string) $row->kind)->label(),
            );
        }

        return ConversationEventType::from((string) $row->event_type)->label();
    }
}
