<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 知识库索引策略。
 *
 *  - Text   : 解析后的 canonical 文本分段，是全文检索与 grep 的存储载体；
 *             同一节点若启用 Vector 索引会补挂向量，全文与向量共享同一节点。
 *  - Vector : 仅用于应用"启用向量索引"勾选项；存储层不写该值，向量是否存在以 embedding_dim 判断。
 *  - Raptor : RAPTOR 摘要节点（kind=summary, level>=1）。叶子直接复用 Text 节点，通过 parent_id 串成树。
 */
enum KnowledgeIndexingStrategy: string implements LabeledEnum
{
    case Text = 'text';
    case Vector = 'vector';
    case Raptor = 'raptor';

    public function label(): string
    {
        return match ($this) {
            self::Text => __('knowledge_base.knowledge_indexing_strategies.text'),
            self::Vector => __('knowledge_base.knowledge_indexing_strategies.vector'),
            self::Raptor => __('knowledge_base.knowledge_indexing_strategies.raptor'),
        };
    }

    /**
     * 给前端勾选项提供一句话说明，帮助用户判断要不要启用。
     */
    public function description(): string
    {
        return match ($this) {
            self::Text => __('knowledge_base.knowledge_indexing_strategies.helper.text'),
            self::Vector => __('knowledge_base.knowledge_indexing_strategies.helper.vector'),
            self::Raptor => __('knowledge_base.knowledge_indexing_strategies.helper.raptor'),
        };
    }

    /**
     * 应用可勾选启用的策略集合（Text 始终启用，不在 UI 中暴露开关）。
     *
     * @return list<self>
     */
    public static function togglableCases(): array
    {
        return [self::Vector, self::Raptor];
    }
}
