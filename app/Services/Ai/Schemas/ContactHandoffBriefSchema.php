<?php

namespace App\Services\Ai\Schemas;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\ArrayOf;

/**
 * 联系人接手简报的 LLM 结构化抽取 schema。
 */
class ContactHandoffBriefSchema
{
    /** 当前会话的一句话接手摘要。 */
    #[SchemaProperty(description: '用联系人使用的语言写一句接手摘要，说明当前诉求、必要状态和关键背景，不超过 240 个字符。', required: true)]
    public string $brief = '';

    /**
     * 坐席可立即执行的下一步。
     *
     * @var list<string>
     */
    #[SchemaProperty(description: '坐席可立即执行的下一步，最多两项；没有明确动作或问题已解决时返回空列表。', required: true)]
    #[ArrayOf('string', allowEmpty: true)]
    public array $next_actions = [];
}
