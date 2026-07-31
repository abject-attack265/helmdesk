<?php

namespace App\Services\KnowledgeBase;

use App\Models\KnowledgeQaAnswer;
use App\Models\KnowledgeQaEntry;
use App\Models\KnowledgeQaQuestion;

/**
 * 把一条问答条目展开成可写入 canonical 节点的内容项（主问题 / 相似问法 / 已启用答案）。
 *
 * 这是检索体系的承重业务逻辑（与具体存储无关），由 WriteCanonicalChunksAction 在写 QA canonical 节点前调用，
 * 决定每条节点的 content / role / 关联 question/answer id。
 */
class KnowledgeQaExpander
{
    /**
     * 展开问答条目。
     *
     * @return list<array{content: string, heading_path: string, qa_question_id: ?string, answer_id: ?string, role: string}>
     */
    public function expand(KnowledgeQaEntry $entry): array
    {
        $entry->loadMissing(['similarQuestions', 'answers']);

        $payload = [
            [
                'content' => (string) $entry->question,
                'heading_path' => 'qa.primary_question',
                'qa_question_id' => null,
                'answer_id' => null,
                'role' => 'qa_primary',
            ],
        ];

        foreach ($entry->similarQuestions as $question) {
            /** @var KnowledgeQaQuestion $question */
            $payload[] = [
                'content' => (string) $question->question,
                'heading_path' => 'qa.similar_question',
                'qa_question_id' => (string) $question->id,
                'answer_id' => null,
                'role' => 'qa_similar',
            ];
        }

        foreach ($entry->answers->where('is_enabled', true) as $answer) {
            /** @var KnowledgeQaAnswer $answer */
            $payload[] = [
                'content' => (string) $answer->answer,
                'heading_path' => 'qa.answer',
                'qa_question_id' => null,
                'answer_id' => (string) $answer->id,
                'role' => 'qa_answer',
            ];
        }

        return $payload;
    }
}
