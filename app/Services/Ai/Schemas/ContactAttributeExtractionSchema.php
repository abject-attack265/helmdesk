<?php

namespace App\Services\Ai\Schemas;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\ArrayOf;

/**
 * 联系人自定义属性提取的 LLM 结构化抽取 schema。
 *
 * 供 NeuronStructuredGenerator 从会话内容中按候选属性清单提取属性赋值，
 * 再由 GenerateContactAttributeValuesAction 校验并落 contact_attribute_values。
 */
class ContactAttributeExtractionSchema
{
    /**
     * 从会话中提取出的属性赋值建议列表。
     *
     * @var list<ContactAttributePickSchema>
     */
    #[SchemaProperty(description: '从会话内容中能确定的属性赋值列表；会话中没有依据的属性不要出现在列表里。', required: true, anyOf: [ContactAttributePickSchema::class])]
    #[ArrayOf(ContactAttributePickSchema::class, allowEmpty: true)]
    public array $picks = [];
}
