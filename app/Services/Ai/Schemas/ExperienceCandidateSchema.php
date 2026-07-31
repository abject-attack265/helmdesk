<?php

namespace App\Services\Ai\Schemas;

use NeuronAI\StructuredOutput\SchemaProperty;
use NeuronAI\StructuredOutput\Validation\Rules\ArrayOf;

/**
 * 经验提炼产出的单条候选经验 schema。
 *
 * 由 ExperienceExtractionSchema 的 candidates 列表引用；conversation_ids 指向转录中标注的会话 ID，
 * PHP 侧会按实际扫描集合过滤，防止模型幻觉出不存在的会话。
 */
class ExperienceCandidateSchema
{
    /** 主问题：访客情境的问句化表述。 */
    #[SchemaProperty(description: '把这类访客问题归纳成一个有代表性的主问题（问句形式），语言遵循指令中的书写要求，剔除订单号、姓名等一次性具体值。', required: true)]
    public string $question = '';

    /**
     * 相似问法。
     *
     * @var list<string>
     */
    #[SchemaProperty(description: '同一问题的其它常见问法（0-5 条），措辞需与主问题有明显差异。', required: false)]
    #[ArrayOf('string', allowEmpty: true)]
    public array $similar_questions = [];

    /** 答复：人工处理方式的提炼。 */
    #[SchemaProperty(description: '人工坐席对这类问题的处理方式与答复要点（含有效话术），泛化措辞、不含一次性或隐私信息。', required: true)]
    public string $answer = '';

    /**
     * 支撑该候选的来源会话 ID。
     *
     * @var list<string>
     */
    #[SchemaProperty(description: '支撑这条经验的会话 ID 列表，取自转录中标注的「会话 ID」，不要编造。', required: true)]
    #[ArrayOf('string', allowEmpty: true)]
    public array $conversation_ids = [];
}
