<?php

namespace App\Actions\Experience;

use App\Actions\KnowledgeBase\BuildKnowledgeBaseSidebarDataAction;
use App\Data\Experience\ExperienceExtractionWindowData;
use App\Data\Experience\ExperienceKnowledgeBaseData;
use App\Data\Experience\FormStartExperienceExtractionData;
use App\Data\Experience\ListExtractableContactItemData;
use App\Data\Experience\ListExtractableConversationItemData;
use App\Data\Experience\ShowCreateExperienceExtractionPagePropsData;
use App\Data\SimplePaginationData;
use App\Data\User\UserOptionData;
use App\Enums\ExperienceExtractionStatus;
use App\Enums\KnowledgeBaseCategory;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ExperienceExtraction;
use App\Models\KnowledgeBase;
use App\Models\User;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 渲染「创建提炼任务」页：绑定问答知识库下，按时间窗口 + 坐席/关键词筛选出可勾选的联系人分页列表，
 * 每个联系人挂上它在窗口内的全部已关闭会话明细（含该库已提炼过标记）。
 */
class ShowCreateExperienceExtractionPageAction
{
    use AsAction;

    /**
     * 组装页面 props；$from / $to 为会话关闭时间窗口（日期粒度，缺省或超跨度上限时收敛，见 ExperienceExtractionWindowData），
     * $timezone 为查看者时区（窗口按其本地日历日取边界），
     * $teammateUserId 筛选「该坐席在窗口内参与过其会话」的联系人，$search 模糊匹配其会话的主题/摘要/最后消息预览。
     */
    public function handle(KnowledgeBase $knowledgeBase, ?Carbon $from, ?Carbon $to, string $timezone, ?string $teammateUserId = null, ?string $search = null, int $page = 1): ShowCreateExperienceExtractionPagePropsData
    {
        $window = ExperienceExtractionWindowData::normalize($from, $to, $timezone);

        [$contacts, $pagination] = $this->selectableContacts($knowledgeBase, $window, $timezone, $teammateUserId, $search, $page);

        $teammateOptions = User::query()->whereHas('membership')
            ->orderBy('name')
            ->get()
            ->map(static fn (User $user): UserOptionData => UserOptionData::fromModel($user))
            ->all();

        $hasRunning = ExperienceExtraction::query()

            ->where('status', ExperienceExtractionStatus::Running)
            ->exists();

        return new ShowCreateExperienceExtractionPagePropsData(
            sidebar: BuildKnowledgeBaseSidebarDataAction::run(),
            knowledge_base: ExperienceKnowledgeBaseData::fromModel($knowledgeBase),
            selectable_contacts: $contacts,
            selectable_pagination: $pagination,
            window: $window,
            max_window_days: ExperienceExtractionWindowData::MAX_WINDOW_DAYS,
            max_conversations: FormStartExperienceExtractionData::MAX_CONVERSATIONS,
            filter_teammate_user_id: $teammateUserId,
            filter_search: $search,
            teammate_options: $teammateOptions,
            has_running_extraction: $hasRunning,
        );
    }

    /**
     * 窗口内人工参与过的联系人（按其最近一次会话关闭时间倒序分页），每人挂上窗口内全部已关闭会话明细。
     *
     * @return array{0: list<ListExtractableContactItemData>, 1: SimplePaginationData}
     */
    private function selectableContacts(KnowledgeBase $knowledgeBase, ExperienceExtractionWindowData $window, string $timezone, ?string $teammateUserId, ?string $search, int $page): array
    {
        $paginator = Contact::query()

            ->select('contacts.*')
            ->whereHas('conversations', function (Builder $query) use ($window, $timezone, $teammateUserId, $search): void {
                $this->constrainQualifyingConversations($query, $window, $timezone, $teammateUserId, $search);
            })
            ->addSelect(['last_closed_at' => Conversation::query()
                ->selectRaw('MAX(closed_at)')
                ->whereColumn('contact_id', 'contacts.id')
                ->whereNotNull('closed_at')
                ->where('closed_at', '>=', $window->startsAt($timezone))
                ->where('closed_at', '<', $window->endsAtExclusive($timezone)),
            ])
            // last_closed_at 不唯一，补 id 保证分页顺序稳定，否则并列联系人可能重复或漏出。
            ->orderByDesc('last_closed_at')
            ->orderByDesc('id')
            ->paginate(SimplePaginationData::DEFAULT_PER_PAGE, ['*'], 'page', max(1, $page));

        /** @var Collection<int, Contact> $contacts */
        $contacts = $paginator->getCollection();
        $conversationsByContact = $this->windowConversationsFor($window, $timezone, $contacts->pluck('id')->all());
        $extractedIds = $this->extractedConversationIds(
            $knowledgeBase,
            $conversationsByContact->flatten()->pluck('id')->all(),
        );

        $items = $contacts
            ->map(static fn (Contact $contact): ListExtractableContactItemData => ListExtractableContactItemData::fromModel(
                $contact,
                $conversationsByContact
                    ->get((string) $contact->id, new Collection)
                    ->map(static fn (Conversation $conversation): ListExtractableConversationItemData => ListExtractableConversationItemData::fromModel(
                        $conversation,
                        isset($extractedIds[(string) $conversation->id]),
                    ))
                    ->all(),
            ))
            ->all();

        return [$items, SimplePaginationData::fromPaginator($paginator)];
    }

    /**
     * 收窄成「让联系人入选」的会话：窗口内已关闭且有人工坐席文本，可再叠加坐席与关键词筛选。
     *
     * 这只决定联系人入不入选；入选后送入提炼的是它窗口内的全部已关闭会话，不受这里的条件限制。
     *
     * @param  Builder<Conversation>  $query
     */
    private function constrainQualifyingConversations(Builder $query, ExperienceExtractionWindowData $window, string $timezone, ?string $teammateUserId, ?string $search): void
    {
        $query
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', $window->startsAt($timezone))
            ->where('closed_at', '<', $window->endsAtExclusive($timezone))
            ->whereHas('messages', function ($messageQuery) use ($teammateUserId): void {
                $messageQuery->where('role', MessageRole::Teammate)
                    ->where('kind', MessageKind::Text)
                    ->whereNotNull('content')
                    ->whereNull('recalled_at');

                if (filled($teammateUserId)) {
                    $messageQuery->where('sender_user_id', $teammateUserId);
                }
            });

        if (filled($search)) {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('subject', 'like', '%'.$search.'%')
                    ->orWhere('summary', 'like', '%'.$search.'%')
                    ->orWhere('last_message_preview', 'like', '%'.$search.'%');
            });
        }
    }

    /**
     * 入选联系人在窗口内的全部已关闭会话，按关闭时间正序、以 contact_id 分组。
     *
     * 含没有人工消息的会话：访客沉默被自动关闭后隔天再来会新开一条会话，提问与人工答复因此分处两条，
     * 把纯访客/AI 的那条也带上，提问的上下文才能跟着答复一起进 LLM。
     *
     * @param  list<string>  $contactIds
     * @return \Illuminate\Support\Collection<string, Collection<int, Conversation>>
     */
    private function windowConversationsFor(ExperienceExtractionWindowData $window, string $timezone, array $contactIds): \Illuminate\Support\Collection
    {
        if ($contactIds === []) {
            return collect();
        }

        return Conversation::query()

            ->with('contact')
            ->whereIn('contact_id', $contactIds)
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', $window->startsAt($timezone))
            ->where('closed_at', '<', $window->endsAtExclusive($timezone))
            ->withCount(['messages as teammate_messages_count' => function ($query): void {
                $query->where('role', MessageRole::Teammate)
                    ->where('kind', MessageKind::Text)
                    ->whereNotNull('content')
                    ->whereNull('recalled_at');
            }])
            ->orderBy('closed_at')
            ->orderBy('id')
            ->get()
            ->groupBy(static fn (Conversation $conversation): string => (string) $conversation->contact_id);
    }

    /**
     * 给定会话里已被本问答库提炼过的那些 ID（值翻成 key 供 isset 判断）。
     *
     * 失败运行未实际消费会话，不计入；标记按绑定问答库计，不同库互不影响。
     * 登记时间须不早于会话当前的 closed_at：会话被提炼后仍可重新打开续聊再关闭，此时 closed_at 会追到登记时间之后，
     * 意味着有未提炼过的新内容，应重新放出来可选。
     *
     * @param  list<string>  $conversationIds
     * @return array<string, int>
     */
    private function extractedConversationIds(KnowledgeBase $knowledgeBase, array $conversationIds): array
    {
        if ($conversationIds === []) {
            return [];
        }

        return DB::table('experience_extraction_conversations as pivot')
            ->join('experience_extractions', 'experience_extractions.id', '=', 'pivot.extraction_id')
            ->join('conversations', 'conversations.id', '=', 'pivot.conversation_id')
            ->where('experience_extractions.knowledge_base_id', $knowledgeBase->id)
            ->whereIn('pivot.conversation_id', $conversationIds)
            ->whereIn('experience_extractions.status', [
                ExperienceExtractionStatus::Running->value,
                ExperienceExtractionStatus::Completed->value,
            ])
            ->whereColumn('pivot.created_at', '>=', 'conversations.closed_at')
            ->pluck('pivot.conversation_id')
            ->flip()
            ->all();
    }

    /**
     * 解析当前应用下的问答知识库与时间窗口/坐席/关键词参数并渲染页面。
     */
    public function asController(Request $request, string $knowledgeBase): Response
    {
        $model = KnowledgeBase::query()

            ->where('category', KnowledgeBaseCategory::Qa)
            ->findOrFail($knowledgeBase);

        $from = $this->parseDate((string) $request->query('from'));
        $to = $this->parseDate((string) $request->query('to'));
        $teammateUserId = trim((string) $request->query('teammate')) ?: null;
        $search = trim((string) $request->query('search')) ?: null;
        $page = (int) $request->query('page', '1');

        return Inertia::render('experiences/Create', $this->handle(
            $model,
            $from,
            $to,
            $request->user()->resolvedTimezone(),
            $teammateUserId,
            $search,
            $page,
        ));
    }

    /**
     * 解析 Y-m-d 日期查询参数；空串或非法值视为未提供（入口边界的兼容解析）。
     */
    private function parseDate(string $value): ?Carbon
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', trim($value));
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
