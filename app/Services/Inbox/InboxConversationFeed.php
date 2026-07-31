<?php

namespace App\Services\Inbox;

use App\Data\Inbox\InboxFiltersData;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Enums\InboxView;
use App\Models\ConversationThread;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * 查询收件箱线程并生成 keyset 分页游标。
 */
class InboxConversationFeed
{
    /** 单次游标分页返回的线程数。 */
    private const int LIST_LIMIT = 50;

    /**
     * 加载一页线程及其当前代表会话。
     *
     * @return array{0: Collection<int, ConversationThread>, 1: string|null}
     */
    public function load(User $user, InboxFiltersData $filters, ?string $cursor): array
    {
        $isClosed = $filters->view === InboxView::Closed;
        $query = $this->buildQuery($user, $filters)
            ->with([
                'currentConversation.contact',
                'currentConversation.receptionPlanVersion.plan',
                'currentConversation.assignedUser',
                'currentConversation.channel',
                'currentConversation.latestMessage',
            ]);

        if (! $isClosed) {
            $query->orderByDesc('is_important');
        }

        $query
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id');

        $decodedCursor = $this->decodeCursor($cursor, $filters);
        if ($decodedCursor !== null) {
            $this->applyCursor($query, $decodedCursor, $isClosed);
        }

        $rows = $query->limit(self::LIST_LIMIT + 1)->get();
        $hasMore = $rows->count() > self::LIST_LIMIT;
        $threads = ($hasMore ? $rows->take(self::LIST_LIMIT) : $rows)->values();

        return [
            $threads,
            $hasMore ? $this->encodeCursor($threads->last(), $filters) : null,
        ];
    }

    /**
     * 按线程 ID 查询当前线程。
     */
    public function find(string $threadId): ConversationThread
    {
        return ConversationThread::query()
            ->with([
                'currentConversation.contact',
                'currentConversation.receptionPlanVersion.plan',
                'currentConversation.assignedUser',
                'currentConversation.channel',
            ])
            ->findOrFail($threadId);
    }

    /**
     * 叠加严格位于游标之后的排序条件。
     *
     * @param  array{a: string, id: string, i?: int}  $cursor
     */
    private function applyCursor(Builder $query, array $cursor, bool $isClosed): void
    {
        if ($isClosed) {
            $query->whereRaw(
                '(last_activity_at, id) < (?, ?)',
                [$cursor['a'], $cursor['id']],
            );

            return;
        }

        $query->whereRaw(
            '(is_important, last_activity_at, id) < (?, ?, ?)',
            [$cursor['i'], $cursor['a'], $cursor['id']],
        );
    }

    /**
     * 编码当前筛选上下文中的下一页位置。
     */
    private function encodeCursor(ConversationThread $thread, InboxFiltersData $filters): string
    {
        $payload = [
            'a' => $thread->last_activity_at->format('Y-m-d H:i:s'),
            'id' => (string) $thread->id,
            'f' => $this->cursorFingerprint($filters),
        ];

        if ($filters->view !== InboxView::Closed) {
            $payload['i'] = (int) $thread->is_important;
        }

        return rtrim(strtr(
            base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)),
            '+/',
            '-_',
        ), '=');
    }

    /**
     * 解析并校验分页游标。
     *
     * @return array{a: string, id: string, i?: int}|null
     */
    private function decodeCursor(?string $cursor, InboxFiltersData $filters): ?array
    {
        if ($cursor === null) {
            return null;
        }

        $json = base64_decode(strtr($cursor, '-_', '+/'), true);
        $decoded = $json !== false ? json_decode($json, true) : null;
        $isClosed = $filters->view === InboxView::Closed;
        $expectedKeys = $isClosed
            ? ['a' => true, 'id' => true, 'f' => true]
            : ['a' => true, 'id' => true, 'f' => true, 'i' => true];

        if (
            ! is_array($decoded)
            || count($decoded) !== count($expectedKeys)
            || array_diff_key($decoded, $expectedKeys) !== []
            || ! is_string($decoded['a'])
            || ! $this->isValidActivityCursor($decoded['a'])
            || ! is_string($decoded['id'])
            || ! Str::isUlid($decoded['id'])
            || ! is_string($decoded['f'])
            || $this->cursorFingerprint($filters) !== $decoded['f']
            || (! $isClosed && (
                ! is_int($decoded['i'])
                || ! in_array($decoded['i'], [0, 1], true)
            ))
        ) {
            throw ValidationException::withMessages([
                'cursor' => __('inbox.errors.invalid_cursor'),
            ]);
        }

        if ($isClosed) {
            return [
                'a' => $decoded['a'],
                'id' => $decoded['id'],
            ];
        }

        return [
            'a' => $decoded['a'],
            'id' => $decoded['id'],
            'i' => $decoded['i'],
        ];
    }

    /**
     * 校验 SQLite 时间游标格式。
     */
    private function isValidActivityCursor(string $activityAt): bool
    {
        if (preg_match('/^(?!0000-)\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $activityAt) !== 1) {
            return false;
        }

        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $activityAt);
        $errors = DateTimeImmutable::getLastErrors();

        return $parsed !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
    }

    /**
     * 生成约束游标使用范围的筛选指纹。
     */
    private function cursorFingerprint(InboxFiltersData $filters): string
    {
        return hash('sha256', json_encode([
            'view' => $filters->view->value,
            'channel_id' => $filters->channel_id,
            'assignee' => $filters->assignee,
            'important_only' => $filters->important_only,
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * 构造收件箱线程查询。
     *
     * @return Builder<ConversationThread>
     */
    private function buildQuery(User $user, InboxFiltersData $filters): Builder
    {
        $query = ConversationThread::query();

        $this->applyView($query, $user, $filters);

        if ($filters->channel_id !== null) {
            $query->where('channel_id', $filters->channel_id);
        }

        if ($filters->important_only) {
            $query->where('is_important', true);
        }

        return $query;
    }

    /**
     * 应用主视图和负责人筛选。
     */
    private function applyView(Builder $query, User $user, InboxFiltersData $filters): void
    {
        match ($filters->view) {
            InboxView::Pending => $query
                ->where('status', ConversationStatus::Open)
                ->where('inbox_status', ConversationInboxStatus::TeammatePending),
            InboxView::Mine => $query
                ->where('status', ConversationStatus::Open)
                ->where('assigned_user_id', $user->id),
            InboxView::Ai => $query
                ->where('status', ConversationStatus::Open)
                ->whereNull('assigned_user_id')
                ->where('inbox_status', ConversationInboxStatus::AiHandling),
            InboxView::Teammates => $query
                ->where('status', ConversationStatus::Open)
                ->whereNotNull('assigned_user_id')
                ->where('assigned_user_id', '!=', $user->id)
                ->where('inbox_status', ConversationInboxStatus::TeammateHandling),
            InboxView::Closed => $query
                ->where('status', ConversationStatus::Closed),
        };

        if ($filters->assignee === InboxFiltersData::ASSIGNEE_UNASSIGNED) {
            $query->whereNull('assigned_user_id');
        } elseif ($filters->assignee !== null) {
            $query->where('assigned_user_id', $filters->assignee);
        }
    }
}
