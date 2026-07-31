<?php

namespace App\Models;

use Database\Factories\KnowledgeQaQuestionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $knowledge_qa_entry_id 所属问答条目
 * @property string $question 相似问法文本，与主问题指向同一答案
 * @property int $sort_order 排序权重
 * @property mixed $use_factory
 * @property int|null $entries_count
 * @property-read KnowledgeQaEntry $entry
 *
 * @method static \Database\Factories\KnowledgeQaQuestionFactory<self> factory($count = null, $state = [])
 */
class KnowledgeQaQuestion extends Model
{
    /**
     * 问答条目的相似问法，用于提升 FAQ 命中率。
     */

    /** @use HasFactory<KnowledgeQaQuestionFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(KnowledgeQaEntry::class, 'knowledge_qa_entry_id');
    }
}
