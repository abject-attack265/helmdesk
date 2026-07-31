<?php

namespace App\Actions\KnowledgeBase;

use App\Data\KnowledgeBase\FormKnowledgeSearchData;
use App\Data\KnowledgeBase\KnowledgeRecallTestResultData;
use App\Data\KnowledgeBase\KnowledgeSearchResultData;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeQaEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 知识库管理页的「检索测试」入口：在后台手动输入一段查询，实时查看某个知识库的召回情况。
 *
 * 复用 SearchKnowledgeBaseAction 同一套检索流水线（grep / semantic / hybrid），
 * 额外把命中富集成人可读结构（来源标题、字段标签、诊断信息），仅作用于单个指定知识库，
 * 不触发页面导航——前端通过 useHttp 拿 JSON 结果渲染在右侧面板。
 */
class RunKnowledgeRecallTestAction
{
    use AsAction;

    public function __construct(
        private readonly SearchKnowledgeBaseAction $search,
    ) {}

    /**
     * 在指定知识库范围内执行检索，并把原始命中富集成面板用结果。
     */
    public function handle(KnowledgeBase $knowledgeBase, FormKnowledgeSearchData $input): KnowledgeRecallTestResultData
    {
        $result = $this->search->handle($input);

        return KnowledgeRecallTestResultData::fromSearchResult(
            mode: $result->mode,
            semanticHits: $result->semantic_hits,
            grepMatches: $result->grep_matches,
            debug: $result->debug,
            documentTitles: $this->resolveDocumentTitles($result),
            qaQuestions: $this->resolveQaQuestions($result),
        );
    }

    /**
     * 批量解析语义命中里出现的文档标题（grep 命中已自带 document_title，无需再查）。
     *
     * @return array<string, string>
     */
    private function resolveDocumentTitles(KnowledgeSearchResultData $result): array
    {
        $ids = [];
        foreach ($result->semantic_hits as $hit) {
            if ($hit->qa_entry_id === null && $hit->document_id !== null) {
                $ids[$hit->document_id] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        return KnowledgeDocument::query()
            ->whereIn('id', array_keys($ids))
            ->pluck('original_filename', 'id')
            ->all();
    }

    /**
     * 批量解析语义命中与 grep 命中里出现的问答主问题，作为 QA 来源标题。
     *
     * @return array<string, string>
     */
    private function resolveQaQuestions(KnowledgeSearchResultData $result): array
    {
        $ids = [];
        foreach ($result->semantic_hits as $hit) {
            if ($hit->qa_entry_id !== null) {
                $ids[$hit->qa_entry_id] = true;
            }
        }
        foreach ($result->grep_matches as $match) {
            if ($match->qa_entry_id !== null) {
                $ids[$match->qa_entry_id] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        return KnowledgeQaEntry::query()
            ->whereIn('id', array_keys($ids))
            ->pluck('question', 'id')
            ->all();
    }

    /**
     * 鉴权后把请求转换为锁定到当前知识库的检索 Data，并以 JSON 形式返回召回结果。
     */
    public function asController(Request $request, string $knowledgeBase): JsonResponse
    {

        $kb = KnowledgeBase::query()

            ->findOrFail($knowledgeBase);

        $data = FormKnowledgeSearchData::validateAndCreate([
            'mode' => $request->input('mode'),
            'query' => $request->input('query'),
            'knowledge_base_ids' => [$kb->id],
        ]);

        return response()->json($this->handle($kb, $data)->toArray());
    }
}
