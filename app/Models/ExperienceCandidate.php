<?php

namespace App\Models;

use App\Enums\ExperienceCandidateStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $extraction_id
 * @property string $question 主问题（访客情境的问句化）
 * @property array $similar_questions 相似问法列表
 * @property string $answer 人工处理方式提炼出的答复
 * @property array $source_conversation_ids 支撑该候选的来源会话 ID 列表（溯源）
 * @property int $conversation_count 支撑该候选的会话数（同类问题热度）
 * @property ExperienceCandidateStatus $status pending / adopted / discarded
 * @property string|null $adopted_qa_entry_id 采纳后生成的知识库 QA 条目
 * @property string|null $handled_by_user_id
 * @property Carbon|null $handled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property mixed $use_factory
 * @property int|null $extractions_count
 * @property int|null $adopted_qa_entries_count
 * @property int|null $handled_bies_count
 * @property-read ExperienceExtraction $extraction
 * @property-read KnowledgeQaEntry|null $adoptedQaEntry
 * @property-read User|null $handledBy
 *
 * @method static \Database\Factories\ExperienceCandidateFactory<self> factory($count = null, $state = [])
 */
class ExperienceCandidate extends Model
{
    /**
     * 候选经验模型。
     *
     * 一次经验提炼运行的产出条目：LLM 从多个人工会话聚合出的「主问题 + 相似问法 + 答复」，
     * 管理员在「经验提炼」页润色后采纳为知识库 QA 问答对（记录 adopted_qa_entry_id）或丢弃；
     * 候选仅作为待人工判断的草稿，采纳后生成的 QA 条目才参与检索。
     */
    use HasFactory, HasUlids;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExperienceCandidateStatus::class,
            'similar_questions' => 'array',
            'source_conversation_ids' => 'array',
            'conversation_count' => 'integer',
            'handled_at' => 'datetime',
        ];
    }

    /**
     * 产出本候选的提炼运行。
     */
    public function extraction(): BelongsTo
    {
        return $this->belongsTo(ExperienceExtraction::class, 'extraction_id');
    }

    /**
     * 采纳后生成的知识库 QA 条目。
     */
    public function adoptedQaEntry(): BelongsTo
    {
        return $this->belongsTo(KnowledgeQaEntry::class, 'adopted_qa_entry_id');
    }

    /**
     * 处理（采纳/丢弃）该候选的管理员。
     */
    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by_user_id');
    }
}
