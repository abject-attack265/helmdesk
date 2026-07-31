<?php

namespace App\Services\Ai\Schemas;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\ArrayOf;

/**
 * LLM 从会话中提取出的单个联系人属性赋值建议。
 *
 * key 必须来自候选属性清单；GenerateContactAttributeValuesAction 会按属性类型
 * 二次校验值的合法性，不合法的单条建议直接丢弃。
 */
class ContactAttributePickSchema
{
    /** 命中的属性 key（必须来自候选属性清单）。 */
    #[SchemaProperty(description: '属性 key，必须严格来自给定候选属性清单。', required: true)]
    public string $key = '';

    /** 单值属性的提取值（字符串形态，按属性类型约定格式）。 */
    #[SchemaProperty(description: '单值属性的值：文本类型为原文；数字为纯数字字符串；日期为 YYYY-MM-DD；布尔为 true 或 false；单选为选项 code。多选属性请留空并使用 values。', required: false)]
    public ?string $value = null;

    /**
     * 多选属性的提取值列表。
     *
     * @var list<string>
     */
    #[SchemaProperty(description: '多选属性的值：选项 code 列表。非多选属性请留空。', required: false)]
    #[ArrayOf('string', allowEmpty: true)]
    public array $values = [];

    /** 提取置信度（0-1）。 */
    #[SchemaProperty(description: '该属性值确实由会话内容支持的置信度，取值 0 到 1。', required: true, min: 0, max: 1)]
    public float $confidence = 0.0;
}
