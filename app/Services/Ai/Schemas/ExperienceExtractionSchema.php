<?php

namespace App\Services\Ai\Schemas;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\ArrayOf;

/**
 * 经验提炼的 LLM 结构化抽取 schema。
 *
 * 供 NeuronStructuredGenerator 使用：输入一批人工会话转录，输出聚合去重后的候选经验列表；
 * 没有值得沉淀的内容时返回空列表。
 */
class ExperienceExtractionSchema
{
    /**
     * 候选经验列表。
     *
     * @var list<ExperienceCandidateSchema>
     */
    #[SchemaProperty(description: '聚合提炼出的候选经验列表；同类问题必须合并为一条；没有可复用内容时返回空列表。', required: true, anyOf: [ExperienceCandidateSchema::class])]
    #[ArrayOf(ExperienceCandidateSchema::class, allowEmpty: true)]
    public array $candidates = [];
}
