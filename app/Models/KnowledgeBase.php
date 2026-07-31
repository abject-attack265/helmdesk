<?php

namespace App\Models;

use App\Enums\KnowledgeBaseCategory;
use App\Enums\KnowledgeIndexingStrategy;
use App\Services\KnowledgeBase\KnowledgeEngine;
use Database\Factories\KnowledgeBaseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $name
 * @property KnowledgeBaseCategory $category 知识库类型：standard 文档 / qa 问答 / wechat_public 公众号
 * @property string|null $avatar_id 知识库头像附件
 * @property string|null $description 知识库描述
 * @property mixed $use_factory
 * @property int|null $avatars_count
 * @property int|null $document_groups_count
 * @property int|null $default_document_groups_count
 * @property int|null $documents_count
 * @property int|null $qa_entries_count
 * @property-read Attachment|null $avatar
 * @property-read Collection|KnowledgeGroup[] $documentGroups
 * @property-read KnowledgeGroup|null $defaultDocumentGroup
 * @property-read Collection|KnowledgeDocument[] $documents
 * @property-read Collection|KnowledgeQaEntry[] $qaEntries
 *
 * @method static \Database\Factories\KnowledgeBaseFactory<self> factory($count = null, $state = [])
 */
class KnowledgeBase extends Model
{
    /**
     * 应用知识库模型，承载文档与问答内容。
     */

    /** @use HasFactory<KnowledgeBaseFactory> */
    use HasFactory, HasUlids;

    public const string DEFAULT_GROUP_NAME = '默认分组';

    protected $guarded = [];

    /**
     * 返回字段类型转换配置。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => KnowledgeBaseCategory::class,
        ];
    }

    /**
     * 返回当前知识库启用的索引策略（按所属应用生效配置解析）。
     *
     * @return list<KnowledgeIndexingStrategy>
     */
    public function enabledIndexingStrategies(): array
    {
        return app(KnowledgeEngine::class)->current()->enabledIndexingStrategies();
    }

    /**
     * 判断指定索引策略当前是否启用。
     */
    public function hasIndexingStrategy(KnowledgeIndexingStrategy $strategy): bool
    {
        return in_array($strategy, $this->enabledIndexingStrategies(), true);
    }

    /**
     * 知识库头像附件。
     */
    public function avatar(): BelongsTo
    {
        return $this->belongsTo(Attachment::class, 'avatar_id');
    }

    /**
     * 知识库文档分组。
     */
    public function documentGroups(): HasMany
    {
        return $this->hasMany(KnowledgeGroup::class)->whereNull('parent_id')->orderBy('sort_order');
    }

    /**
     * 知识库自动创建的默认分组。
     */
    public function defaultDocumentGroup(): HasOne
    {
        return $this->hasOne(KnowledgeGroup::class)->where('is_default', true);
    }

    /**
     * 知识库下的文档。
     */
    public function documents(): HasMany
    {
        return $this->hasMany(KnowledgeDocument::class);
    }

    /**
     * 知识库下的问答条目。
     */
    public function qaEntries(): HasMany
    {
        return $this->hasMany(KnowledgeQaEntry::class);
    }
}
