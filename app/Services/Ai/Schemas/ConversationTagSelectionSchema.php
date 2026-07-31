<?php

namespace App\Services\Ai\Schemas;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\ArrayOf;

/**
 * 会话标签选择的 LLM 结构化抽取 schema。
 *
 * 供 NeuronStructuredGenerator 从受控词表中挑选适用标签，再由 GenerateConversationTagsAction
 * 校验 tag_id 并映射成 GeneratedConversationTagData。
 */
class ConversationTagSelectionSchema
{
    /**
     * 从候选词表中选出的标签建议列表。
     *
     * @var list<ConversationTagPickSchema>
     */
    #[SchemaProperty(description: '从候选词表中选出的、适用于本次会话的标签列表；不适用则返回空列表。', required: true, anyOf: [ConversationTagPickSchema::class])]
    #[ArrayOf(ConversationTagPickSchema::class, allowEmpty: true)]
    public array $tags = [];
}
