<?php

namespace App\Data\KnowledgeBase;

use Spatie\LaravelData\Data;

/**
 * 问答知识库条目创建表单 Data，支持主问题、相似问法和多答案。
 */
class FormCreateKnowledgeQaEntryData extends Data
{
    public const int QUESTION_MAX_LENGTH = 500;

    public const int ANSWER_MAX_LENGTH = 200_000;

    public const int SIMILAR_QUESTION_MAX_COUNT = 20;

    public const int ANSWER_MAX_COUNT = 10;

    /**
     * @param  list<string>  $similar_questions
     * @param  list<string>  $answers
     */
    public function __construct(
        public string $question,
        public array $answers,
        public array $similar_questions = [],
        public ?string $group_id = null,
    ) {}

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:'.self::QUESTION_MAX_LENGTH],
            'similar_questions' => ['nullable', 'array', 'max:'.self::SIMILAR_QUESTION_MAX_COUNT],
            'similar_questions.*' => ['nullable', 'string', 'max:'.self::QUESTION_MAX_LENGTH],
            'answers' => ['required', 'array', 'min:1', 'max:'.self::ANSWER_MAX_COUNT],
            'answers.*' => ['required', 'string', 'max:'.self::ANSWER_MAX_LENGTH],
            'group_id' => ['nullable', 'string'],
        ];
    }

    /**
     * 返回去重后的相似问法，排除空值和与主问题完全相同的内容。
     *
     * @return list<string>
     */
    public function normalizedSimilarQuestions(): array
    {
        $primaryQuestion = trim($this->question);
        $seen = [];
        $questions = [];

        foreach ($this->similar_questions as $question) {
            $question = trim((string) $question);
            if ($question === '' || $question === $primaryQuestion || isset($seen[$question])) {
                continue;
            }

            $seen[$question] = true;
            $questions[] = $question;
        }

        return $questions;
    }

    /**
     * 返回去空后的答案列表，保持用户输入顺序。
     *
     * @return list<string>
     */
    public function normalizedAnswers(): array
    {
        $answers = [];

        foreach ($this->answers as $answer) {
            $answer = trim((string) $answer);
            if ($answer === '') {
                continue;
            }

            $answers[] = $answer;
        }

        return $answers;
    }
}
