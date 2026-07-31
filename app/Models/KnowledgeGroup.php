<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $knowledge_base_id 所属知识库
 * @property string|null $parent_id 父分组，支持树形层级
 * @property string $name
 * @property bool $is_default 是否为默认分组（未归类文档归入此处）
 * @property int $sort_order 同级排序权重
 * @property int|null $knowledge_bases_count
 * @property int|null $parents_count
 * @property int|null $childrens_count
 * @property int|null $documents_count
 * @property int|null $qa_entries_count
 * @property-read KnowledgeBase $knowledgeBase
 * @property-read KnowledgeGroup|null $parent
 * @property-read Collection|KnowledgeGroup[] $children
 * @property-read Collection|KnowledgeDocument[] $documents
 * @property-read Collection|KnowledgeQaEntry[] $qaEntries
 */
class KnowledgeGroup extends Model
{
    /**
     * 知识库分组，支持最多 2 级嵌套，用于将同一知识库下的文档按主题归类。
     */
    use HasUlids;

    protected $guarded = [];

    /**
     * 返回字段类型转换配置。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * 分组下的文档。
     */
    public function documents(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class, 'group_id');
    }

    /**
     * 分组下的问答条目。
     */
    public function qaEntries(): HasMany
    {
        return $this->hasMany(KnowledgeQaEntry::class, 'group_id');
    }
}
