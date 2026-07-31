<?php

namespace App\Services\Ai\Schemas;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\ArrayOf;

/**
 * 收件箱 AI 回复助手的 LLM 结构化抽取 schema。
 *
 * 供 NeuronStructuredGenerator 一次性生成多条候选客服回复，再由 PolishInboxReplyAction
 * 映射成 InboxReplyPolishCandidateData。
 */
class InboxReplyPolishSchema
{
    /**
     * 候选客服回复正文列表。
     *
     * @var list<string>
     */
    #[SchemaProperty(description: '生成的候选客服回复正文列表，按推荐顺序排列，每条都是可直接发送的完整回复。', required: true, min: 1, max: 3)]
    #[ArrayOf('string')]
    public array $candidates = [];
}
