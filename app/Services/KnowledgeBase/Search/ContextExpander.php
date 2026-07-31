<?php

namespace App\Services\KnowledgeBase\Search;

use App\Enums\KnowledgeIndexingStrategy;
use App\Enums\KnowledgeNodeKind;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeNode;
use App\Models\KnowledgeQaEntry;
use Illuminate\Support\Collection;

/**
 * 把单条 SearchHit 扩展成"带上下文的命中"。
 *
 *  - canonical 文本节点：可选给前后相邻段拼一段更长的展示文；
 *  - Raptor summary 节点：把 children_ids 中的叶子文本读出来一起带；
 *  - QA：带上完整问答的 question / answer 结构，方便 Agent 直接呈现。
 *
 * 这里输出仍然是 KnowledgeSearchHit（metadata 中追加 expanded 信息），
 * 让 Agent 工具最终能拿到"既能小步定位、又能整块阅读"的复合形态。
 */
class ContextExpander
{
    /**
     * canonical 文本节点附近最多前后各取多少条相邻段做拼接。
     */
    private const int ADJACENT_NEIGHBORS = 1;

    /**
     * Raptor summary 展开时最多带多少条叶子片段，避免占满 prompt。
     */
    private const int RAPTOR_LEAF_LIMIT = 4;

    /**
     * 给一批命中追加上下文。返回的命中数量与传入一致，但 metadata.context 会带上扩展内容。
     *
     * @param  list<KnowledgeSearchHit>  $hits
     * @return list<KnowledgeSearchHit>
     */
    public function expand(array $hits): array
    {
        if ($hits === []) {
            return [];
        }

        $nodeIds = [];
        foreach ($hits as $hit) {
            $nodeIds[] = $hit->knowledgeNodeId;
        }
        $primaryNodes = KnowledgeNode::query()
            ->whereIn('id', array_unique($nodeIds))
            ->get()
            ->keyBy('id');

        $adjacentByNode = $this->loadAdjacentNeighbors($primaryNodes);
        $raptorLeavesByNode = $this->loadRaptorLeaves($primaryNodes);
        $qaEntriesById = $this->loadQaEntries($primaryNodes);
        $documentTitleById = $this->loadDocumentTitles($primaryNodes);

        $output = [];
        foreach ($hits as $hit) {
            $node = $primaryNodes->get($hit->knowledgeNodeId);
            $context = [];

            if ($node !== null) {
                $context['kind'] = $node->kind->value;
                $context['strategy'] = $node->strategy->value;

                if ($node->kind === KnowledgeNodeKind::Segment) {
                    $context['adjacent'] = $adjacentByNode[$hit->knowledgeNodeId] ?? [];
                }
                if ($node->strategy === KnowledgeIndexingStrategy::Raptor && $node->kind === KnowledgeNodeKind::Summary) {
                    $context['raptor_leaves'] = $raptorLeavesByNode[$hit->knowledgeNodeId] ?? [];
                }
                if ($node->qa_entry_id !== null) {
                    $qa = $qaEntriesById[(string) $node->qa_entry_id] ?? null;
                    if ($qa !== null) {
                        $context['qa'] = $qa;
                        $context['qa_role'] = (string) ($node->metadata['qa_role'] ?? '');
                    }
                }
                $context['document_title'] = $node->document_id !== null
                    ? ($documentTitleById[$node->document_id] ?? null)
                    : null;
            }

            $metadata = $hit->metadata;
            $metadata['context'] = $context;

            $output[] = $hit->with(['metadata' => $metadata]);
        }

        return $output;
    }

    /**
     * 给文档型 canonical 段拉前后 N 段。
     *
     * @param  Collection<string, KnowledgeNode>  $primaryNodes
     * @return array<string, list<array{node_id: string, byte_start: int|null, byte_end: int|null, content: string}>>
     */
    private function loadAdjacentNeighbors($primaryNodes): array
    {
        $byDocument = [];
        foreach ($primaryNodes as $node) {
            if ($node->document_id === null || $node->strategy !== KnowledgeIndexingStrategy::Text) {
                continue;
            }
            $documentId = (string) $node->document_id;
            $byDocument[$documentId] ??= [];
            $byDocument[$documentId][] = $node;
        }
        if ($byDocument === []) {
            return [];
        }

        $documentIds = array_keys($byDocument);
        $allTextNodes = KnowledgeNode::query()
            ->whereIn('document_id', $documentIds)
            ->where('strategy', KnowledgeIndexingStrategy::Text)
            ->where('kind', KnowledgeNodeKind::Segment)
            ->orderBy('document_id')
            ->orderByRaw('COALESCE(byte_start, 0) ASC')
            ->orderBy('id')
            ->get(['id', 'document_id', 'byte_start', 'byte_end', 'content']);

        $nodesByDoc = [];
        foreach ($allTextNodes as $node) {
            $nodesByDoc[(string) $node->document_id][] = $node;
        }

        $adjacentByNode = [];
        foreach ($primaryNodes as $primary) {
            if ($primary->document_id === null || $primary->strategy !== KnowledgeIndexingStrategy::Text) {
                continue;
            }
            $list = $nodesByDoc[(string) $primary->document_id] ?? [];
            $index = null;
            foreach ($list as $i => $candidate) {
                if ((string) $candidate->id === (string) $primary->id) {
                    $index = $i;
                    break;
                }
            }
            if ($index === null) {
                continue;
            }

            $neighbors = [];
            $start = max(0, $index - self::ADJACENT_NEIGHBORS);
            $end = min(count($list) - 1, $index + self::ADJACENT_NEIGHBORS);
            for ($i = $start; $i <= $end; $i++) {
                if ($i === $index) {
                    continue;
                }
                $sibling = $list[$i];
                $neighbors[] = [
                    'node_id' => (string) $sibling->id,
                    'byte_start' => $sibling->byte_start,
                    'byte_end' => $sibling->byte_end,
                    'content' => (string) $sibling->content,
                ];
            }
            $adjacentByNode[(string) $primary->id] = $neighbors;
        }

        return $adjacentByNode;
    }

    /**
     * 给 Raptor summary 节点展开 children 文本。
     *
     * @param  Collection<string, KnowledgeNode>  $primaryNodes
     * @return array<string, list<array{node_id: string, content: string}>>
     */
    private function loadRaptorLeaves($primaryNodes): array
    {
        $childrenIdsByNode = [];
        $allChildrenIds = [];
        foreach ($primaryNodes as $node) {
            if ($node->strategy !== KnowledgeIndexingStrategy::Raptor || $node->kind !== KnowledgeNodeKind::Summary) {
                continue;
            }
            $children = $node->metadata['children_ids'] ?? [];
            if (! is_array($children) || $children === []) {
                continue;
            }
            $limited = array_slice($children, 0, self::RAPTOR_LEAF_LIMIT);
            $childrenIdsByNode[(string) $node->id] = $limited;
            foreach ($limited as $childId) {
                if (is_string($childId)) {
                    $allChildrenIds[] = $childId;
                }
            }
        }

        if ($allChildrenIds === []) {
            return [];
        }

        $childNodes = KnowledgeNode::query()
            ->whereIn('id', array_unique($allChildrenIds))
            ->get(['id', 'content'])
            ->keyBy('id');

        $result = [];
        foreach ($childrenIdsByNode as $parentId => $childIds) {
            $result[$parentId] = [];
            foreach ($childIds as $childId) {
                if (! $childNodes->has($childId)) {
                    continue;
                }
                $child = $childNodes->get($childId);
                $result[$parentId][] = [
                    'node_id' => (string) $child->id,
                    'content' => (string) $child->content,
                ];
            }
        }

        return $result;
    }

    /**
     * 给所有命中里涉及到的 QA 条目一次性拉出"主问题 + 启用答案 + 相似问"轻量结构。
     *
     * 这一步对 grep / vector / fulltext 出来的 QA 节点都生效：节点本身只承载单段文本
     * （问题或某一条答案），但 Agent 需要的是"问题 + 全部启用答案"的完整对照，所以
     * 在这里集中预取，避免每条命中各自 N+1 拉一次。
     *
     * @param  Collection<string, KnowledgeNode>  $primaryNodes
     * @return array<string, array{
     *     entry_id: string,
     *     primary_question: string,
     *     answers: list<array{id: string, content: string}>,
     *     similar_questions: list<array{id: string, content: string}>
     * }>
     */
    private function loadQaEntries($primaryNodes): array
    {
        $entryIds = [];
        foreach ($primaryNodes as $node) {
            if ($node->qa_entry_id === null) {
                continue;
            }
            $entryIds[] = (string) $node->qa_entry_id;
        }
        $entryIds = array_values(array_unique($entryIds));
        if ($entryIds === []) {
            return [];
        }

        $entries = KnowledgeQaEntry::query()
            ->with([
                'answers' => fn ($q) => $q->where('is_enabled', true)->orderBy('sort_order'),
                'similarQuestions' => fn ($q) => $q->orderBy('sort_order'),
            ])
            ->whereIn('id', $entryIds)
            ->get();

        $output = [];
        foreach ($entries as $entry) {
            $output[(string) $entry->id] = [
                'entry_id' => (string) $entry->id,
                'primary_question' => (string) $entry->question,
                'answers' => $entry->answers->map(static fn ($answer): array => [
                    'id' => (string) $answer->id,
                    'content' => (string) $answer->answer,
                ])->values()->all(),
                'similar_questions' => $entry->similarQuestions->map(static fn ($question): array => [
                    'id' => (string) $question->id,
                    'content' => (string) $question->question,
                ])->values()->all(),
            ];
        }

        return $output;
    }

    /**
     * 一次性把所有命中文档的标题拉回来，键到 document_id 供 expand() 写入 context.document_title。
     *
     * @param  Collection<string, KnowledgeNode>  $primaryNodes
     * @return array<string, string>
     */
    private function loadDocumentTitles($primaryNodes): array
    {
        $documentIds = [];
        foreach ($primaryNodes as $node) {
            if ($node->document_id === null) {
                continue;
            }
            $documentIds[] = $node->document_id;
        }
        $documentIds = array_values(array_unique($documentIds));
        if ($documentIds === []) {
            return [];
        }

        return KnowledgeDocument::query()
            ->whereIn('id', $documentIds)
            ->get(['id', 'original_filename'])
            ->mapWithKeys(static fn (KnowledgeDocument $doc): array => [
                $doc->id => $doc->original_filename,
            ])
            ->all();
    }
}
