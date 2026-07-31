<?php

namespace App\Services\Ai\Schemas;

use NeuronAI\StructuredOutput\SchemaProperty;

/**
 * 会话摘要的 LLM 结构化抽取 schema。
 */
class ConversationSummarySchema
{
    /** 用访客语言写的一段连续会话摘要。 */
    #[SchemaProperty(description: '用访客使用的语言，对整段会话写一段连续、客观的摘要，覆盖访客诉求与处理进展。', required: true)]
    public string $summary = '';
}
