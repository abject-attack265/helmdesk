<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 暴露给 Agent 的知识库检索模式。
 *
 *  - Grep     : 字面 / 大小写不敏感的精确子串匹配，命中带行号 / 列号 / 上下文，适合查"代码 / 编号 / 关键字面"。
 *  - Semantic : 语义检索；具体包含哪几路 retriever 取决于应用配置：
 *               · 始终包含 TNTSearch 全文检索；
 *               · 启用向量索引 → 加入向量召回；
 *               · 启用 RAPTOR → 加入摘要节点向量召回；
 *               · 配置了 rerank 模型 → 在 RRF 之后追加一遍重排。
 *  - Hybrid   : 同时跑 grep 与 semantic，两路结果以独立数组形式返回给 Agent，由 Agent 自行取舍。
 */
enum KnowledgeSearchMode: string implements LabeledEnum
{
    case Grep = 'grep';
    case Semantic = 'semantic';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Grep => __('knowledge_search.modes.grep'),
            self::Semantic => __('knowledge_search.modes.semantic'),
            self::Hybrid => __('knowledge_search.modes.hybrid'),
        };
    }

    /**
     * Semantic / Hybrid 都会触发语义子流程。
     */
    public function needsSemantic(): bool
    {
        return $this === self::Semantic || $this === self::Hybrid;
    }

    /**
     * Grep / Hybrid 都会触发 grep 子流程。
     */
    public function needsGrep(): bool
    {
        return $this === self::Grep || $this === self::Hybrid;
    }
}
