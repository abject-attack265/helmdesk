<?php

namespace App\Services\Ai\Schemas;

use NeuronAI\StructuredOutput\SchemaProperty;

/**
 * LLM 从受控词表中选出的单个会话标签建议。
 *
 * tag_id 必须来自候选词表；GenerateConversationTagsAction 会再次校验其在词表内。
 */
class ConversationTagPickSchema
{
    /** 命中的标签 ID（必须来自候选词表）。 */
    #[SchemaProperty(description: '选中的标签 ID，必须严格来自给定候选词表中的 tag_id。', required: true)]
    public string $tag_id = '';

    /** 命中置信度（0-1）。 */
    #[SchemaProperty(description: '该标签适用于本次会话的置信度，取值 0 到 1。', required: true, min: 0, max: 1)]
    public float $confidence = 0.0;

    /** 选择该标签的简短理由。 */
    #[SchemaProperty(description: '选择该标签的简短理由。', required: false)]
    public ?string $reason = null;
}
