<?php

namespace App\Services\KnowledgeBase;

use App\Actions\KnowledgeBase\SearchKnowledgeBaseAction;
use App\Data\KnowledgeBase\FormKnowledgeSearchData;
use App\Enums\KnowledgeSearchMode;
use App\Exceptions\BusinessException;
use Closure;
use NeuronAI\Tools\ArrayProperty;
use NeuronAI\Tools\PropertyType;
use NeuronAI\Tools\Tool;
use NeuronAI\Tools\ToolProperty;

/**
 * 装配 knowledge_search 工具（知识库检索），供任意挂载知识库的 agent 复用：
 * 接待 agent（ReceptionToolFactory）与 AI 助手（AiChatStreamRunner）都用它。
 *
 * knowledge_base_ids 不暴露给 LLM：构造工具时绑定本次允许检索的知识库，调用时整体下推，PHP 侧负责范围限定。
 * 检索走 SearchKnowledgeBaseAction。
 */
class KnowledgeSearchToolFactory
{
    public function __construct(
        private readonly SearchKnowledgeBaseAction $searchAction,
    ) {}

    /**
     * 构造 knowledge_search 工具。
     *
     * @param  list<string>  $knowledgeBaseIds  本次允许检索的知识库 ID（空表示当前应用全部）
     * @param  Closure(int):void|null  $onSearched  每次检索后回调，入参为本次命中条目数；供调用方做接地观测
     */
    public function buildKnowledgeSearchTool(array $knowledgeBaseIds, ?Closure $onSearched = null): Tool
    {
        return Tool::make(
            'knowledge_search',
            '检索知识库。mode=grep 字面匹配；mode=semantic 向量+全文语义检索；mode=hybrid 两者都返回。query 可给 1-8 个不同措辞/角度。',
        )
            ->addProperty(new ToolProperty(
                'mode',
                PropertyType::STRING,
                '检索方式：grep（字面子串匹配）/ semantic（向量+全文语义）/ hybrid（两者并返回）。',
                true,
                [KnowledgeSearchMode::Grep->value, KnowledgeSearchMode::Semantic->value, KnowledgeSearchMode::Hybrid->value],
            ))
            ->addProperty(new ArrayProperty(
                'query',
                '一个或多个检索查询（1-8 条）。拿不准时给 1-4 个不同措辞或角度，服务端会合并。',
                true,
                new ToolProperty('item', PropertyType::STRING, '单条查询', true),
            ))
            ->setCallable(function (?string $mode, ?array $query) use ($knowledgeBaseIds, $onSearched): array {
                $modeEnum = KnowledgeSearchMode::tryFrom(trim((string) $mode));
                if ($modeEnum === null) {
                    return ['error' => 'mode_required'];
                }

                $queries = array_values(array_filter(
                    $query ?? [],
                    static fn (mixed $q): bool => is_string($q) && trim($q) !== '',
                ));

                $data = FormKnowledgeSearchData::from([
                    'mode' => $modeEnum->value,
                    'knowledge_base_ids' => $knowledgeBaseIds,
                    'query' => $queries,
                ]);

                try {
                    $result = $this->searchAction->handle($data);
                } catch (BusinessException $exception) {
                    if ($exception->getMessage() === __('knowledge_search.errors.knowledge_base_inaccessible')) {
                        return ['error' => 'knowledge_base_inaccessible'];
                    }

                    if ($exception->getMessage() === __('knowledge_search.errors.query_required')) {
                        return ['error' => 'query_required'];
                    }

                    throw $exception;
                }
                if ($onSearched !== null) {
                    $onSearched(count($result->semantic_hits) + count($result->grep_matches));
                }

                return $result->toArray();
            });
    }
}
