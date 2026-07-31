<?php

namespace App\Data\Inbox;

use App\Enums\InboxView;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelData\Data;

/**
 * 收件箱会话筛选条件。
 * 来自收件箱地址栏查询参数，pages/inbox/InboxToolbar.vue 用它还原筛选状态。
 */
class InboxFiltersData extends Data
{
    public const string ASSIGNEE_UNASSIGNED = 'unassigned';

    public function __construct(
        public InboxView $view,
        public ?string $channel_id,
        public ?string $assignee,
        /** 聊天记录搜索词：仅回显给前端驱动消息级搜索与 URL 还原，不参与会话列表过滤。 */
        public ?string $search,
        public bool $important_only,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $view = self::parseView($request->query('view'));

        $channelId = $request->query('channel');
        $assignee = $request->query('assignee');
        $search = $request->query('search');
        $normalizedSearch = is_string($search) ? trim($search) : '';

        return new self(
            view: $view,
            channel_id: is_string($channelId) && $channelId !== '' ? $channelId : null,
            assignee: is_string($assignee) && $assignee !== '' ? $assignee : null,
            search: $normalizedSearch !== '' ? mb_substr($normalizedSearch, 0, 80) : null,
            important_only: $request->boolean('important'),
        );
    }

    public function isAssigneeUnassigned(): bool
    {
        return $this->assignee === self::ASSIGNEE_UNASSIGNED;
    }

    private static function parseView(mixed $rawView): InboxView
    {
        if ($rawView === null || $rawView === '') {
            return InboxView::Pending;
        }

        if (is_string($rawView) && ($view = InboxView::tryFrom($rawView)) instanceof InboxView) {
            return $view;
        }

        throw ValidationException::withMessages([
            'view' => __('validation.in', ['attribute' => 'view']),
        ]);
    }
}
