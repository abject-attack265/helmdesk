<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 向量索引的文档分段策略，前端在检索配置面板的分段方式下拉中展示。
 */
enum KnowledgeChunkingStrategy: string implements LabeledEnum
{
    case Fixed = 'fixed';
    case Semantic = 'semantic';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => __('knowledge_base.chunking_strategies.fixed'),
            self::Semantic => __('knowledge_base.chunking_strategies.semantic'),
        };
    }
}
