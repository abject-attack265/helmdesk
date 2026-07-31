<?php

namespace App\Data\KnowledgeBase;

use App\Enums\KnowledgeSearchMode;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * Agent 通过工具调用知识库检索时的入参。
 *
 * 设计原则：暴露给大模型的字段尽量少，复杂决策（top_k、是否重排、是否走 RAPTOR）
 * 内部根据应用配置自动决定。
 *
 *  - mode：grep / semantic / hybrid 三选一；
 *  - knowledge_base_ids：可选，本应用下要缩小检索范围的知识库 ULID 列表；空列表表示全部知识库；
 *  - query：可以是单条字符串，也可以是字符串数组——这样模型一次工具调用就能问多个角度。
 */
class FormKnowledgeSearchData extends Data
{
    /**
     * 单次工具调用允许的最大 query 条数；超出部分在 normalizedQueries() 中截断。
     */
    public const int MAX_QUERIES = 8;

    /**
     * 单条 query 最大字符数。超长 query 通常是 Agent 把整段 prompt 塞了进来，
     * 会让 grep LIKE 退化成大常量扫描、全文 query token 爆量；超出部分在 normalizedQueries() 中截断。
     */
    public const int MAX_QUERY_LENGTH = 200;

    /**
     * @param  string|list<string>  $query
     * @param  list<string>  $knowledge_base_ids
     */
    public function __construct(
        public string|array $query,
        public KnowledgeSearchMode $mode,
        public array $knowledge_base_ids = [],
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        $modes = array_map(static fn (KnowledgeSearchMode $mode): string => $mode->value, KnowledgeSearchMode::cases());

        return [
            'mode' => ['required', 'string', Rule::in($modes)],
            'query' => ['required'],
            'query.*' => ['string'],
            'knowledge_base_ids' => ['sometimes', 'array'],
            'knowledge_base_ids.*' => ['string'],
        ];
    }

    /**
     * 把 query 字段统一展开成字符串数组，供 Action 内部使用。
     *
     * 单条 query 做 trim + 去空 + 按 MAX_QUERY_LENGTH 截断；整体去重（保留首次出现顺序）
     * 后按 MAX_QUERIES 截断，避免 Agent 传重复 query 时产生重复的嵌入调用与重复命中。
     *
     * @return list<string>
     */
    public function normalizedQueries(): array
    {
        $raw = is_array($this->query) ? $this->query : [$this->query];
        $output = [];
        foreach ($raw as $item) {
            $trimmed = trim($item);
            if ($trimmed === '') {
                continue;
            }
            if (mb_strlen($trimmed) > self::MAX_QUERY_LENGTH) {
                $trimmed = mb_substr($trimmed, 0, self::MAX_QUERY_LENGTH);
            }
            if (in_array($trimmed, $output, true)) {
                continue;
            }
            $output[] = $trimmed;
            if (count($output) >= self::MAX_QUERIES) {
                break;
            }
        }

        return $output;
    }
}
