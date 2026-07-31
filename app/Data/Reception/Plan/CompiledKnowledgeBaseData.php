<?php

namespace App\Data\Reception\Plan;

use App\Enums\KnowledgeBaseCategory;
use App\Models\KnowledgeBase;
use LogicException;
use Spatie\LaravelData\Data;

/**
 * 接待方案版本 compiled_config 中的知识库快照项。
 * 编译时由方案选中的知识库固化（id + 名称 + 用途描述），运行时接待 agent 据此限定 knowledge_search 检索范围。
 */
class CompiledKnowledgeBaseData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public ?KnowledgeBaseCategory $category,
    ) {}

    /**
     * 编译时从知识库模型固化快照。
     */
    public static function fromModel(KnowledgeBase $knowledgeBase): self
    {
        return new self(
            id: (string) $knowledgeBase->id,
            name: $knowledgeBase->name,
            description: filled($knowledgeBase->description) ? $knowledgeBase->description : null,
            category: $knowledgeBase->category,
        );
    }

    /**
     * 从版本 compiled_config 中恢复快照项。
     *
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        if (! isset($raw['id']) || ! is_string($raw['id']) || $raw['id'] === '') {
            throw new LogicException('Compiled knowledge base snapshot must contain id.');
        }

        return new self(
            id: $raw['id'],
            name: (string) ($raw['name'] ?? ''),
            description: isset($raw['description']) && filled($raw['description']) ? (string) $raw['description'] : null,
            category: isset($raw['category']) && filled($raw['category']) ? KnowledgeBaseCategory::from((string) $raw['category']) : null,
        );
    }
}
