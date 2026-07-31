<?php

namespace App\Data\Experience;

use App\Data\KnowledgeBase\FormCreateKnowledgeQaEntryData;
use Spatie\LaravelData\Data;

/**
 * 采纳候选经验表单 Data。
 * 提交来源：resources/js/pages/experiences/Results.vue 的采纳对话框（管理员润色后的最终问答内容）；
 * 落库目标为任务绑定的问答库，不由表单指定。
 */
class FormAdoptExperienceCandidateData extends Data
{
    /**
     * @param  list<string>  $similar_questions
     */
    public function __construct(
        public string $question,
        public string $answer,
        public array $similar_questions = [],
    ) {}

    /** @return array<string, list<mixed>> */
    public static function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:'.FormCreateKnowledgeQaEntryData::QUESTION_MAX_LENGTH],
            'answer' => ['required', 'string', 'max:'.FormCreateKnowledgeQaEntryData::ANSWER_MAX_LENGTH],
            'similar_questions' => ['nullable', 'array', 'max:'.FormCreateKnowledgeQaEntryData::SIMILAR_QUESTION_MAX_COUNT],
            'similar_questions.*' => ['nullable', 'string', 'max:'.FormCreateKnowledgeQaEntryData::QUESTION_MAX_LENGTH],
        ];
    }
}
