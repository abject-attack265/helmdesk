<?php

namespace App\Actions\KnowledgeBase;

use App\Data\EnumOptionData;
use App\Data\KnowledgeBase\KnowledgeBaseData;
use App\Data\KnowledgeBase\ListKnowledgeDocumentItemData;
use App\Data\KnowledgeBase\ListKnowledgeQaEntryItemData;
use App\Data\KnowledgeBase\ShowKnowledgeBaseListPagePropsData;
use App\Data\SimplePaginationData;
use App\Enums\KnowledgeBaseCategory;
use App\Enums\KnowledgeDocumentSourceType;
use App\Enums\KnowledgeDocumentStatus;
use App\Enums\KnowledgeQaEntryStatus;
use App\Enums\KnowledgeSearchMode;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeGroup;
use App\Models\KnowledgeQaEntry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 返回知识库列表页所需的知识库、分组、筛选项和当前列表。
 */
class ListKnowledgeBasesAction
{
    use AsAction;

    private const array STATUS_FILTER_VALUES = [
        'pending',
        'processing',
        'indexed',
        'failed',
    ];

    /**
     * 查询应用下所有知识库及其分组树，组装页面数据。
     *
     * $status 使用页面统一的四种状态筛选值，文档库和问答库各自转换为实际状态。
     */
    public function handle(?string $selectedKnowledgeBaseId = null, ?string $selectedGroupId = null, ?string $search = null, ?string $status = null, int $page = 1, int $perPage = SimplePaginationData::DEFAULT_PER_PAGE): ShowKnowledgeBaseListPagePropsData
    {
        $search = $this->normalizeSearch($search);
        $status = $this->resolveStatus($status);
        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);

        $allKnowledgeBases = KnowledgeBase::query()
            ->with([
                'avatar',
                'documentGroups.children',
                'documentGroups.children.children',
            ])
            ->oldest('created_at')
            ->oldest('id')
            ->get();
        $knowledgeBaseListData = $allKnowledgeBases
            ->map(fn (KnowledgeBase $kb) => KnowledgeBaseData::fromModel($kb))
            ->all();

        $selectedKnowledgeBaseData = null;
        $resolvedSelectedGroupId = null;
        $documentList = [];
        $documentPagination = SimplePaginationData::placeholder($perPage);
        $qaEntryList = [];
        $qaEntryPagination = SimplePaginationData::placeholder($perPage);
        if (filled($selectedKnowledgeBaseId)) {
            $selected = $allKnowledgeBases->firstWhere('id', $selectedKnowledgeBaseId);
            if ($selected) {
                $selectedKnowledgeBaseData = KnowledgeBaseData::fromModel($selected);
                if (filled($selectedGroupId)) {
                    $resolvedSelectedGroupId = $this->resolveGroupId($selected, $selectedGroupId);
                }
                if ($selected->category === KnowledgeBaseCategory::Qa) {
                    [$qaEntryList, $qaEntryPagination] = $this->loadQaEntryList($selected, $resolvedSelectedGroupId, $search, $status, $page, $perPage);
                } else {
                    [$documentList, $documentPagination] = $this->loadDocumentList($selected, $resolvedSelectedGroupId, $search, $status, $page, $perPage);
                }
            }
        }

        $statusOptions = $this->statusFilterOptions();

        return new ShowKnowledgeBaseListPagePropsData(
            knowledge_base_list: $knowledgeBaseListData,
            selected_knowledge_base: $selectedKnowledgeBaseData,
            selected_group_id: $resolvedSelectedGroupId,
            search: $search,
            current_status: $status,
            document_list: $documentList,
            document_list_pagination: $documentPagination,
            qa_entry_list: $qaEntryList,
            qa_entry_list_pagination: $qaEntryPagination,
            document_status_options: $statusOptions,
            qa_status_options: $statusOptions,
            category_options: EnumOptionData::fromCases(KnowledgeBaseCategory::creatableCases()),
            search_mode_options: EnumOptionData::fromCases(KnowledgeSearchMode::cases()),
        );
    }

    /**
     * 分页加载当前选中知识库下的文档列表；指定父分组时包含其子分组文档，否则返回全部文档。
     *
     * @return array{0: list<ListKnowledgeDocumentItemData>, 1: SimplePaginationData}
     */
    private function loadDocumentList(KnowledgeBase $knowledgeBase, ?string $groupId, ?string $search, ?string $status, int $page, int $perPage): array
    {
        $query = KnowledgeDocument::query()
            ->where('knowledge_base_id', $knowledgeBase->id)
            ->whereIn('source_type', [
                KnowledgeDocumentSourceType::Upload,
                KnowledgeDocumentSourceType::Manual,
            ]);

        if ($groupId !== null) {
            $query->whereIn('group_id', $this->documentScopeGroupIds($knowledgeBase, $groupId));
        }

        if ($status !== null) {
            $query->whereIn('status', $this->documentStatusesForFilter($status));
        }

        if ($search !== null) {
            $query->where('original_filename', 'like', "%{$search}%");
        }

        $paginator = $query
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $list = $paginator->getCollection()
            ->map(fn (KnowledgeDocument $document) => ListKnowledgeDocumentItemData::fromModel($document, $knowledgeBase))
            ->all();

        $pagination = SimplePaginationData::fromPaginator($paginator);

        return [$list, $pagination];
    }

    /**
     * 分页加载当前选中问答知识库下的问答列表；搜索覆盖主问题、相似问法和答案。
     *
     * @return array{0: list<ListKnowledgeQaEntryItemData>, 1: SimplePaginationData}
     */
    private function loadQaEntryList(KnowledgeBase $knowledgeBase, ?string $groupId, ?string $search, ?string $status, int $page, int $perPage): array
    {
        $query = KnowledgeQaEntry::query()
            ->with(['similarQuestions', 'answers'])
            ->where('knowledge_base_id', $knowledgeBase->id);

        if ($groupId !== null) {
            $query->whereIn('group_id', $this->documentScopeGroupIds($knowledgeBase, $groupId));
        }

        if ($status !== null) {
            $query->whereIn('status', $this->qaStatusesForFilter($status));
        }

        if ($search !== null) {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('question', 'like', "%{$search}%")
                    ->orWhereHas('similarQuestions', function ($questionQuery) use ($search): void {
                        $questionQuery->where('question', 'like', "%{$search}%");
                    })
                    ->orWhereHas('answers', function ($answerQuery) use ($search): void {
                        $answerQuery->where('answer', 'like', "%{$search}%");
                    });
            });
        }

        $paginator = $query
            ->latest('created_at')
            ->latest('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $list = $paginator->getCollection()
            ->map(fn (KnowledgeQaEntry $entry) => ListKnowledgeQaEntryItemData::fromModel($entry))
            ->all();

        $pagination = SimplePaginationData::fromPaginator($paginator);

        return [$list, $pagination];
    }

    /**
     * 返回当前分组视图应包含的分组 ID；父分组包含直接子分组，子分组只包含自身。
     *
     * @return list<string>
     */
    private function documentScopeGroupIds(KnowledgeBase $knowledgeBase, string $groupId): array
    {
        foreach ($knowledgeBase->documentGroups as $group) {
            if ($group->id === $groupId) {
                return [
                    (string) $group->id,
                    ...$group->children
                        ->map(fn (KnowledgeGroup $child): string => (string) $child->id)
                        ->all(),
                ];
            }

            foreach ($group->children as $child) {
                if ($child->id === $groupId) {
                    return [(string) $child->id];
                }
            }
        }

        throw new \LogicException("Knowledge group [{$groupId}] is outside the loaded knowledge base tree.");
    }

    /**
     * 渲染知识库列表页面，从 URL query 解析当前选中的知识库和分组。
     */
    public function asController(Request $request): Response
    {
        $search = $request->query('search');
        $status = $request->query('status');

        return Inertia::render('knowledgeBase/List', $this->handle(
            selectedKnowledgeBaseId: $request->query('kb'),
            selectedGroupId: $request->query('group'),
            search: is_string($search) ? $search : null,
            status: is_string($status) ? $status : null,
            page: max(1, (int) $request->query('page', 1)),
        )->toArray());
    }

    /**
     * 去除搜索词首尾空白，空串归一为 null，便于 handle() 内统一判断是否启用模糊匹配。
     */
    private function normalizeSearch(?string $search): ?string
    {
        $search = $search !== null ? trim($search) : '';

        return $search !== '' ? $search : null;
    }

    /**
     * 将 URL query 中的状态筛选转换为可复用的状态值。
     */
    private function resolveStatus(?string $status): ?string
    {
        return $status !== null && in_array($status, self::STATUS_FILTER_VALUES, true)
            ? $status
            : null;
    }

    /**
     * 返回列表共用的四种状态筛选项。
     *
     * @return list<EnumOptionData>
     */
    private function statusFilterOptions(): array
    {
        return [
            new EnumOptionData('pending', __('knowledge_base.documents.statuses.pending')),
            new EnumOptionData('processing', __('knowledge_base.documents.statuses.indexing')),
            new EnumOptionData('indexed', __('knowledge_base.documents.statuses.indexed')),
            new EnumOptionData('failed', __('knowledge_base.documents.statuses.failed')),
        ];
    }

    /**
     * 将页面状态筛选值转换为文档实际状态。
     *
     * @return list<string>
     */
    private function documentStatusesForFilter(string $status): array
    {
        return match ($status) {
            'pending' => [KnowledgeDocumentStatus::Pending->value],
            'processing' => [
                KnowledgeDocumentStatus::Parsing->value,
                KnowledgeDocumentStatus::Parsed->value,
                KnowledgeDocumentStatus::Indexing->value,
            ],
            'indexed' => [KnowledgeDocumentStatus::Indexed->value],
            'failed' => [KnowledgeDocumentStatus::Failed->value],
        };
    }

    /**
     * 将页面状态筛选值转换为问答实际状态。
     *
     * @return list<string>
     */
    private function qaStatusesForFilter(string $status): array
    {
        return match ($status) {
            'pending' => [KnowledgeQaEntryStatus::Pending->value],
            'processing' => [KnowledgeQaEntryStatus::Indexing->value],
            'indexed' => [KnowledgeQaEntryStatus::Indexed->value],
            'failed' => [KnowledgeQaEntryStatus::Failed->value],
        };
    }

    /**
     * 校验 group_id 是否属于当前知识库；不属于则返回 null（前端会回退到全部文档）。
     */
    private function resolveGroupId(KnowledgeBase $knowledgeBase, string $groupId): ?string
    {
        foreach ($knowledgeBase->documentGroups as $group) {
            if ($group->id === $groupId) {
                return $group->id;
            }
            foreach ($group->children as $child) {
                if ($child->id === $groupId) {
                    return $child->id;
                }
            }
        }

        return null;
    }
}
