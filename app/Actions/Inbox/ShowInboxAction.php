<?php

namespace App\Actions\Inbox;

use App\Actions\Contact\ShowContactConversationTimelineAction;
use App\Actions\Conversation\GetContactConversationTagAggregatesAction;
use App\Data\Conversation\ConversationContactSummaryData;
use App\Data\Conversation\ConversationSummaryData;
use App\Data\Conversation\ListConversationItemData;
use App\Data\CurrentUserContextData;
use App\Data\CustomAttribute\ContactAttributeFieldData;
use App\Data\EnumOptionData;
use App\Data\Inbox\EnabledWebChannelData;
use App\Data\Inbox\InboxContactProfileData;
use App\Data\Inbox\InboxFiltersData;
use App\Data\Inbox\InboxSelectionData;
use App\Data\Inbox\InboxTabCountsData;
use App\Data\Tag\TagOptionData;
use App\Data\User\UserOptionData;
use App\Enums\ChannelType;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Enums\InboxPane;
use App\Enums\ReceptionLanguage;
use App\Enums\ReplyAssistantMode;
use App\Enums\ReplyPolishTone;
use App\Enums\TagScope;
use App\Models\AttributeDefinition;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\ContactAttributeValue;
use App\Models\Conversation;
use App\Models\ConversationThread;
use App\Models\Tag;
use App\Models\User;
use App\Services\Inbox\InboxConversationFeed;
use App\Services\Reception\ChannelAiAvailability;
use App\Services\Reception\ReceptionActivityRegistry;
use App\Services\Translation\TranslationProviderPool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 组装收件箱线程列表、当前会话和联系人上下文。
 */
class ShowInboxAction
{
    use AsAction;

    /**
     * 注入收件箱页面所需服务。
     */
    public function __construct(
        private readonly InboxConversationFeed $conversationFeed,
        private readonly ShowContactConversationTimelineAction $contactTimelineAction,
        private readonly ChannelAiAvailability $aiAvailability,
        private readonly ReceptionActivityRegistry $activityRegistry,
        private readonly GetContactConversationTagAggregatesAction $conversationTagAggregates,
        private readonly TranslationProviderPool $translationPool,
    ) {}

    /**
     * 组装收件箱页面 props，查询型字段通过闭包支持 Inertia partial reload。
     *
     * @return array<string, mixed>
     */
    public function handle(
        User $user,
        InboxFiltersData $filters,
        ?string $threadId = null,
        ?string $cursor = null,
        InboxPane $pane = InboxPane::Thread,
    ): array {
        $this->validateFilters($filters);

        $feed = null;
        $loadFeed = function () use (&$feed, $user, $filters, $cursor): array {
            if ($feed === null) {
                [$threads, $nextCursor] = $this->conversationFeed->load($user, $filters, $cursor);
                $feed = [$threads, $nextCursor];
            }

            return $feed;
        };

        $selectionResolved = null;
        $resolveSelection = function () use (&$selectionResolved, $user, $threadId, $pane, $loadFeed): array {
            if ($selectionResolved === null) {
                $selectionResolved = $pane === InboxPane::List
                    ? [null, null]
                    : $this->resolveSelection(
                        user: $user,
                        threads: $threadId === null ? $loadFeed()[0] : collect(),
                        threadId: $threadId,
                    );
            }

            return $selectionResolved;
        };

        return [
            'current_view' => $filters->view,
            'current_channel_id' => $filters->channel_id,
            'current_assignee' => $filters->assignee,
            'current_search' => $filters->search,
            'current_important_only' => $filters->important_only,
            'current_pane' => $pane,
            'current_thread_id' => fn () => $resolveSelection()[0],
            'enabled_web_channels' => fn () => $this->loadEnabledWebChannels(),
            'teammates' => fn () => $this->loadTeammates($user),
            'conversation_list' => Inertia::merge(fn () => $loadFeed()[0]
                ->map(fn (ConversationThread $thread) => ListConversationItemData::fromModel(
                    $thread,
                    $this->unreadCount($thread, $user),
                ))
                ->all())->append(matchOn: 'thread_id'),
            'conversation_list_next_cursor' => fn () => $loadFeed()[1],
            'selection' => fn () => $resolveSelection()[1],
            'available_contact_tags' => fn () => $this->loadAvailableTagsForScope(TagScope::Contact),
            'available_conversation_tags' => fn () => $this->loadAvailableTagsForScope(TagScope::Conversation),
            'reception_language_options' => EnumOptionData::fromCases(ReceptionLanguage::cases()),
            'reply_assistant_mode_options' => EnumOptionData::fromCases(ReplyAssistantMode::cases()),
            'reply_polish_tone_options' => EnumOptionData::fromCases(ReplyPolishTone::cases()),
            'tab_counts' => fn () => $this->computeTabCounts($user),
        ];
    }

    /**
     * 统计各收件箱视图的待关注数量。
     */
    private function computeTabCounts(User $user): InboxTabCountsData
    {
        $pending = ConversationThread::query()
            ->where('status', ConversationStatus::Open)
            ->where('inbox_status', ConversationInboxStatus::TeammatePending)
            ->count();

        return new InboxTabCountsData(
            pending: $pending,
            ai: 0,
            mine: $this->countMyOpenThreadsWithUnreadVisitorMessages($user),
            teammates: 0,
        );
    }

    /**
     * 统计当前用户负责且存在未读访客消息的开放线程数。
     */
    private function countMyOpenThreadsWithUnreadVisitorMessages(User $user): int
    {
        return ConversationThread::query()
            ->where('status', ConversationStatus::Open)
            ->where('assigned_user_id', $user->id)
            ->whereHas(
                'currentConversation',
                fn (Builder $query) => $query->where('unread_visitor_message_count', '>', 0),
            )
            ->count();
    }

    /**
     * 返回后台收件箱页面。
     */
    public function asController(Request $request): Response
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $filters = InboxFiltersData::fromRequest($request);
        $navigation = $request->validate([
            'thread_id' => ['sometimes', 'required', 'string', 'ulid'],
            'cursor' => ['sometimes', 'required', 'string'],
            'pane' => ['sometimes', 'required', Rule::enum(InboxPane::class)],
        ]);
        $pane = isset($navigation['pane'])
            ? InboxPane::from($navigation['pane'])
            : InboxPane::Thread;
        $threadId = $navigation['thread_id'] ?? null;

        if ($pane === InboxPane::List && $threadId !== null) {
            throw ValidationException::withMessages([
                'pane' => __('inbox.errors.invalid_pane'),
            ]);
        }

        return Inertia::render('Inbox', $this->handle(
            user: User::query()->findOrFail($ctx->user_id),
            filters: $filters,
            threadId: $threadId,
            cursor: $navigation['cursor'] ?? null,
            pane: $pane,
        ));
    }

    /**
     * 校验收件箱筛选条件。
     */
    private function validateFilters(InboxFiltersData $filters): void
    {
        if (
            $filters->channel_id !== null
            && ! $this->channelExists($filters->channel_id)
        ) {
            throw ValidationException::withMessages([
                'channel' => __('validation.exists', ['attribute' => 'channel']),
            ]);
        }

        if (
            $filters->assignee !== null
            && $filters->assignee !== InboxFiltersData::ASSIGNEE_UNASSIGNED
            && ! $this->userExists($filters->assignee)
        ) {
            throw ValidationException::withMessages([
                'assignee' => __('validation.exists', ['attribute' => 'assignee']),
            ]);
        }
    }

    /**
     * 返回当前用户在指定线程中的未读访客消息数。
     */
    private function unreadCount(ConversationThread $thread, User $user): int
    {
        $conversation = $this->currentConversation($thread);

        return (string) $conversation->assigned_user_id === (string) $user->id
            ? (int) $conversation->unread_visitor_message_count
            : 0;
    }

    /**
     * 查询可用于收件箱筛选的网站渠道。
     *
     * @return EnabledWebChannelData[]
     */
    private function loadEnabledWebChannels(): array
    {
        return Channel::query()
            ->where('type', ChannelType::Web)
            ->orderBy('name')
            ->get()
            ->map(fn (Channel $channel) => EnabledWebChannelData::fromModel($channel))
            ->all();
    }

    /**
     * 查询当前应用的其他成员选项。
     *
     * @return UserOptionData[]
     */
    private function loadTeammates(User $currentUser): array
    {
        return User::query()
            ->whereHas('membership')
            ->where('users.id', '!=', $currentUser->id)
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => UserOptionData::fromModel($user))
            ->all();
    }

    /**
     * 按适用维度查询标签选项。
     *
     * @return TagOptionData[]
     */
    private function loadAvailableTagsForScope(TagScope $scope): array
    {
        return Tag::query()
            ->whereHas('tagGroup', fn (Builder $query) => $query->where('scope', $scope->value))
            ->orderBy('name')
            ->get()
            ->map(fn (Tag $tag) => TagOptionData::fromModel($tag))
            ->all();
    }

    /**
     * 解析当前线程及其右侧面板数据。
     *
     * @param  Collection<int, ConversationThread>  $threads
     * @return array{0: string|null, 1: InboxSelectionData|null}
     */
    private function resolveSelection(
        User $user,
        Collection $threads,
        ?string $threadId,
    ): array {
        $selectedThread = $threadId !== null
            ? $this->conversationFeed->find($threadId)
            : $threads->first();

        if ($selectedThread === null) {
            return [null, null];
        }

        $selected = $this->currentConversation($selectedThread);
        $selected->loadMissing(['contact.tags', 'tags', 'receptionPlanVersion.plan', 'assignedUser', 'channel']);
        $selected->loadCount(['messages as display_message_count' => Conversation::displayMessageCountQuery()]);
        $contact = $selected->contact;

        if (! $contact instanceof Contact) {
            Log::warning('收件箱线程当前会话缺少联系人', [
                'thread_id' => (string) $selectedThread->id,
                'conversation_id' => (string) $selected->id,
                'contact_id' => $selected->contact_id !== null
                    ? (string) $selected->contact_id
                    : null,
            ]);

            throw new LogicException("收件箱线程 {$selectedThread->id} 的当前会话缺少联系人。");
        }

        $stitched = $this->contactTimelineAction->handle($contact, viewer: $user);
        $customAttributes = $this->buildCustomAttributeFields($contact);
        $conversationTagAggregates = $this->conversationTagAggregates->handle((string) $contact->id);

        $isOpen = $selected->status === ConversationStatus::Open;
        $selectionThreadId = (string) $selectedThread->id;
        $isAiOwned = $selected->assigned_user_id === null
            && $selected->inbox_status === ConversationInboxStatus::AiHandling;
        $isAssignedToCurrentUser = $selected->assigned_user_id !== null
            && (string) $selected->assigned_user_id === (string) $user->id;
        $isAssignedToAnotherUser = $selected->assigned_user_id !== null
            && ! $isAssignedToCurrentUser;
        $canClaim = $isOpen && (
            $isAiOwned
            || $selected->inbox_status === ConversationInboxStatus::TeammatePending
            || (
                $isAssignedToAnotherUser
                && $selected->inbox_status === ConversationInboxStatus::TeammateHandling
            )
        );
        $canTransferToTeammate = $isOpen
            && $isAssignedToCurrentUser
            && $selected->inbox_status === ConversationInboxStatus::TeammateHandling;
        $canUseAi = $this->aiAvailability->canUseAi($selected->channel);
        // 排队中的会话仅在 AI 可用时显示交接入口；人工接待中的会话始终可以释放负责人。
        $canReleaseToAi = $isOpen
            && (
                ($isAssignedToCurrentUser && $selected->inbox_status === ConversationInboxStatus::TeammateHandling)
                || ($selected->inbox_status === ConversationInboxStatus::TeammatePending && $canUseAi)
            );
        $releaseToAiWillUseAi = $canReleaseToAi && $canUseAi;

        return [
            $selectionThreadId,
            new InboxSelectionData(
                conversation: ConversationSummaryData::fromModel($selected),
                agent_activity: $this->activityRegistry->current((string) $selected->id),
                contact: ConversationContactSummaryData::fromModel($contact),
                contact_profile: InboxContactProfileData::fromModel($contact, $customAttributes, $conversationTagAggregates),
                stitched_timeline: $stitched,
                can_reply: $isOpen && ! $isAiOwned && ! $isAssignedToAnotherUser,
                can_claim: $canClaim,
                can_transfer_to_teammate: $canTransferToTeammate,
                can_release_to_ai: $canReleaseToAi,
                release_to_ai_will_use_ai: $releaseToAiWillUseAi,
                can_close: $isOpen && ! $isAssignedToAnotherUser,
                can_reopen: ! $isOpen,
                can_translate_messages: $this->translationPool->hasUsable(),
            ),
        ];
    }

    /**
     * 返回线程已加载的当前代表会话。
     */
    private function currentConversation(ConversationThread $thread): Conversation
    {
        $conversation = $thread->currentConversation;

        if (! $conversation instanceof Conversation) {
            Log::warning('收件箱线程缺少当前代表会话', [
                'thread_id' => (string) $thread->id,
                'current_conversation_id' => (string) $thread->current_conversation_id,
            ]);

            throw new LogicException("收件箱线程 {$thread->id} 缺少当前代表会话。");
        }

        return $conversation;
    }

    /**
     * 组装联系人资料面板里的自定义属性字段。
     *
     * @return ContactAttributeFieldData[]
     */
    private function buildCustomAttributeFields(Contact $contact): array
    {
        $activeDefinitions = AttributeDefinition::query()
            ->active()
            ->ordered()
            ->get();

        $contactValues = ContactAttributeValue::query()
            ->where('contact_id', $contact->id)
            ->with('definition')
            ->get()
            ->keyBy('definition_id');

        $deletedWithValues = $contactValues
            ->filter(fn (ContactAttributeValue $value) => $value->definition?->trashed())
            ->map(fn (ContactAttributeValue $value) => $value->definition)
            ->filter();

        $fields = [];

        foreach ($activeDefinitions->merge($deletedWithValues) as $definition) {
            $value = $contactValues->get($definition->id);

            $fields[] = new ContactAttributeFieldData(
                definition_id: $definition->id,
                key: $definition->key,
                name: $definition->name,
                description: $definition->description,
                type: $definition->type->value,
                type_label: $definition->type->label(),
                config: $definition->config,
                value: $value?->value(),
                source: $value?->source?->value,
                source_label: $value?->source?->label(),
                deleted_at: $definition->deleted_at?->toIso8601String(),
                is_editable: ! $definition->trashed(),
            );
        }

        return $fields;
    }

    /**
     * 判断渠道筛选值是否属于当前应用。
     */
    private function channelExists(string $channelId): bool
    {
        return Channel::query()
            ->whereKey($channelId)
            ->exists();
    }

    /**
     * 判断负责人筛选值是否属于当前应用。
     */
    private function userExists(string $userId): bool
    {
        return User::query()
            ->whereHas('membership')
            ->whereKey($userId)
            ->exists();
    }
}
