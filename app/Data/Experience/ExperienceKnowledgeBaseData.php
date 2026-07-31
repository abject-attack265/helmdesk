<?php

namespace App\Data\Experience;

use App\Models\KnowledgeBase;
use Spatie\LaravelData\Data;

/**
 * 经验提炼页面的问答知识库上下文（面包屑与返回链接），
 * 用于 resources/js/pages/experiences/ 下各页。
 */
class ExperienceKnowledgeBaseData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    /**
     * 从知识库模型构造上下文。
     */
    public static function fromModel(KnowledgeBase $knowledgeBase): self
    {
        return new self(
            id: (string) $knowledgeBase->id,
            name: (string) $knowledgeBase->name,
        );
    }
}
