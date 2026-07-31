<?php

namespace App\Services\KnowledgeBase;

use App\Enums\KnowledgeBaseCategory;
use App\Exceptions\BusinessException;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeQaEntry;

/**
 * 问答条目子表（相似问法 / 答案）的写入助手。
 *
 * Create / Update Action 把"重建 similar_questions / answers"这段同构逻辑都委托到这里，
 * 通过文本匹配做增量 sync：内容没变的子记录保留 ID 不动，避免重复 churn 数据库 + 向量节点。
 */
class KnowledgeQaEntryWriter
{
    /**
     * 问答条目只允许写入问答知识库；非问答知识库拒绝提交。
     */
    public function assertQaKnowledgeBase(?KnowledgeBase $knowledgeBase): void
    {
        if ($knowledgeBase !== null && $knowledgeBase->category === KnowledgeBaseCategory::Qa) {
            return;
        }

        throw new BusinessException(__('knowledge_base.qa.errors.not_qa_knowledge_base'));
    }

    /**
     * 同步相似问法：同文本保留旧记录、只更新 sort_order，未出现的旧记录删除，新的插入。
     *
     * @param  list<string>  $questions
     */
    public function syncSimilarQuestions(KnowledgeQaEntry $entry, array $questions): void
    {
        $existing = $entry->similarQuestions()->get();
        $existingByText = $existing->keyBy('question');
        $keptIds = [];

        foreach ($questions as $index => $question) {
            $existingRow = $existingByText->get($question);
            if ($existingRow !== null) {
                if ((int) $existingRow->sort_order !== $index) {
                    $existingRow->update(['sort_order' => $index]);
                }
                $keptIds[(string) $existingRow->id] = true;

                continue;
            }
            $entry->similarQuestions()->create([
                'question' => $question,
                'sort_order' => $index,
            ]);
        }

        $obsoleteIds = $existing
            ->reject(fn ($row) => isset($keptIds[(string) $row->id]))
            ->pluck('id')
            ->all();
        if ($obsoleteIds !== []) {
            $entry->similarQuestions()->whereIn('id', $obsoleteIds)->delete();
        }
    }

    /**
     * 同步答案：增量 upsert + 删除未出现的旧记录，并维护 sort_order / is_default。
     *
     * @param  list<string>  $answers
     */
    public function syncAnswers(KnowledgeQaEntry $entry, array $answers): void
    {
        $existing = $entry->answers()->get();
        $existingByText = $existing->keyBy('answer');
        $keptIds = [];

        foreach ($answers as $index => $answer) {
            $existingRow = $existingByText->get($answer);
            $isDefault = $index === 0;
            if ($existingRow !== null) {
                $needsUpdate = (int) $existingRow->sort_order !== $index
                    || (bool) $existingRow->is_default !== $isDefault
                    || (bool) $existingRow->is_enabled !== true;
                if ($needsUpdate) {
                    $existingRow->update([
                        'sort_order' => $index,
                        'is_default' => $isDefault,
                        'is_enabled' => true,
                    ]);
                }
                $keptIds[(string) $existingRow->id] = true;

                continue;
            }
            $entry->answers()->create([
                'answer' => $answer,
                'is_default' => $isDefault,
                'is_enabled' => true,
                'sort_order' => $index,
            ]);
        }

        $obsoleteIds = $existing
            ->reject(fn ($row) => isset($keptIds[(string) $row->id]))
            ->pluck('id')
            ->all();
        if ($obsoleteIds !== []) {
            $entry->answers()->whereIn('id', $obsoleteIds)->delete();
        }
    }
}
