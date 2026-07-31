<?php

namespace App\Models;

use App\Enums\AttachmentPurpose;
use App\Enums\KnowledgeDocumentIndexingStatus;
use App\Enums\KnowledgeDocumentParseStatus;
use App\Enums\KnowledgeDocumentSourceType;
use App\Enums\KnowledgeDocumentStatus;
use App\Enums\KnowledgeIndexingStrategy;
use Database\Factories\KnowledgeDocumentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $knowledge_base_id 所属知识库
 * @property string $group_id 所属文档分组
 * @property string|null $uploaded_by_user_id 上传者用户
 * @property string $original_filename 原始文件名
 * @property string $mime_type 文件 MIME 类型
 * @property int $byte_size 文件字节大小
 * @property string|null $extension 文件扩展名
 * @property string|null $checksum_sha256 文件内容 SHA256，用于去重/校验
 * @property KnowledgeDocumentSourceType $source_type 来源类型：upload 上传 / manual 手动录入
 * @property KnowledgeDocumentStatus $status 综合状态：pending/parsing/parsed/indexing/indexed/failed，由各阶段派生
 * @property string|null $error_message 综合状态对应的错误信息
 * @property string|null $content 原始文本内容
 * @property KnowledgeDocumentParseStatus $parse_status 解析状态：pending/processing/succeeded/failed/skipped
 * @property string|null $parse_error 解析失败原因
 * @property Carbon|null $parsed_at 解析完成时间
 * @property string|null $parsed_content_format 解析后正文格式，如 markdown
 * @property string|null $parsed_content 解析后正文，供切分与索引使用
 * @property array|null $parse_metadata 解析元数据，如页数、分块信息
 * @property KnowledgeDocumentIndexingStatus $vector_status 向量索引状态：idle/pending/processing/succeeded/failed
 * @property string|null $vector_error 向量索引失败原因
 * @property Carbon|null $vector_indexed_at 向量索引完成时间
 * @property KnowledgeDocumentIndexingStatus $raptor_status RAPTOR 摘要索引状态：idle/pending/processing/succeeded/failed
 * @property string|null $raptor_error RAPTOR 索引失败原因
 * @property Carbon|null $raptor_indexed_at RAPTOR 索引完成时间
 * @property mixed $use_factory
 * @property int|null $knowledge_bases_count
 * @property int|null $groups_count
 * @property int|null $uploaded_bies_count
 * @property int|null $original_files_count
 * @property-read KnowledgeBase $knowledgeBase
 * @property-read KnowledgeGroup $group
 * @property-read User|null $uploadedBy
 * @property-read Attachment|null $originalFile
 *
 * @method static \Database\Factories\KnowledgeDocumentFactory<self> factory($count = null, $state = [])
 */
class KnowledgeDocument extends Model
{
    /**
     * 知识库文档模型，保存上传文件、解析结果和索引阶段状态。
     */

    /** @use HasFactory<KnowledgeDocumentFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    /**
     * 返回字段类型转换配置。
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'byte_size' => 'integer',
            'source_type' => KnowledgeDocumentSourceType::class,
            'status' => KnowledgeDocumentStatus::class,
            'parse_status' => KnowledgeDocumentParseStatus::class,
            'parsed_at' => 'datetime',
            'parse_metadata' => 'array',
            'vector_status' => KnowledgeDocumentIndexingStatus::class,
            'vector_indexed_at' => 'datetime',
            'raptor_status' => KnowledgeDocumentIndexingStatus::class,
            'raptor_indexed_at' => 'datetime',
        ];
    }

    /**
     * 返回指定策略当前的索引状态。Text 始终启用且与解析共生，这里复用 parse_status 的就绪态做近似展示。
     */
    public function indexingStatusFor(KnowledgeIndexingStrategy $strategy): KnowledgeDocumentIndexingStatus
    {
        return match ($strategy) {
            KnowledgeIndexingStrategy::Vector => $this->vector_status,
            KnowledgeIndexingStrategy::Raptor => $this->raptor_status,
            KnowledgeIndexingStrategy::Text => $this->parse_status === KnowledgeDocumentParseStatus::Succeeded
                ? KnowledgeDocumentIndexingStatus::Succeeded
                : KnowledgeDocumentIndexingStatus::Pending,
        };
    }

    /**
     * 更新指定策略的阶段状态：自动维护对应 *_error / *_indexed_at 字段，
     * 写入后立即调用 refreshOverallStatus() 把综合状态同步到 status 列。
     */
    public function updateStageStatus(
        KnowledgeIndexingStrategy $strategy,
        KnowledgeDocumentIndexingStatus $status,
        ?string $error = null,
        ?KnowledgeBase $knowledgeBase = null,
    ): self {
        $prefix = match ($strategy) {
            KnowledgeIndexingStrategy::Vector => 'vector',
            KnowledgeIndexingStrategy::Raptor => 'raptor',
            KnowledgeIndexingStrategy::Text => null,
        };

        if ($prefix === null) {
            // Text 索引状态由解析阶段反映；这里无需写库。
            return $this->refreshOverallStatus($knowledgeBase);
        }

        $updates = [
            $prefix.'_status' => $status,
            $prefix.'_error' => $error,
        ];

        if ($status === KnowledgeDocumentIndexingStatus::Succeeded) {
            $updates[$prefix.'_indexed_at'] = now();
        } elseif ($status === KnowledgeDocumentIndexingStatus::Idle
            || $status === KnowledgeDocumentIndexingStatus::Pending) {
            $updates[$prefix.'_indexed_at'] = null;
        }

        $this->forceFill($updates)->save();
        $this->refreshOverallStatus($knowledgeBase);

        return $this;
    }

    /**
     * 从数据库最新阶段状态派生 `status` 列。
     * 先 refresh 一次以兼容并发写场景；调用方在多次 updateStageStatus 后无需再单独调用。
     */
    public function refreshOverallStatus(?KnowledgeBase $knowledgeBase = null): self
    {
        $knowledgeBase ??= $this->knowledgeBase;
        if ($knowledgeBase === null) {
            return $this;
        }

        $this->refresh();
        $this->forceFill(['status' => $this->deriveOverallStatus($knowledgeBase)])->save();

        return $this;
    }

    /**
     * 根据 parse 与各启用策略状态派生综合状态：
     *  - parse 失败 / 任一启用策略失败 → Failed
     *  - parse 未完成 → Pending / Parsing
     *  - parse 完成但启用策略全部 Idle → Parsed
     *  - 任一启用策略 Pending/Processing → Indexing
     *  - 所有启用策略 Succeeded → Indexed
     */
    public function deriveOverallStatus(KnowledgeBase $knowledgeBase): KnowledgeDocumentStatus
    {
        if ($this->parse_status === KnowledgeDocumentParseStatus::Failed) {
            return KnowledgeDocumentStatus::Failed;
        }

        if ($this->parse_status === KnowledgeDocumentParseStatus::Pending) {
            return KnowledgeDocumentStatus::Pending;
        }

        if ($this->parse_status === KnowledgeDocumentParseStatus::Processing) {
            return KnowledgeDocumentStatus::Parsing;
        }

        $strategies = $knowledgeBase->enabledIndexingStrategies();
        if ($strategies === []) {
            return KnowledgeDocumentStatus::Indexed;
        }

        $hasFailed = false;
        $hasPending = false;
        $allSucceeded = true;
        $anyTouched = false;
        foreach ($strategies as $strategy) {
            $status = $this->indexingStatusFor($strategy);

            if ($status === KnowledgeDocumentIndexingStatus::Failed) {
                $hasFailed = true;
            }

            if ($status === KnowledgeDocumentIndexingStatus::Pending
                || $status === KnowledgeDocumentIndexingStatus::Processing) {
                $hasPending = true;
                $anyTouched = true;
            }

            if ($status === KnowledgeDocumentIndexingStatus::Succeeded) {
                $anyTouched = true;
            } else {
                $allSucceeded = false;
            }
        }

        if ($hasFailed) {
            return KnowledgeDocumentStatus::Failed;
        }

        if ($hasPending) {
            return KnowledgeDocumentStatus::Indexing;
        }

        if ($allSucceeded) {
            return KnowledgeDocumentStatus::Indexed;
        }

        return $anyTouched ? KnowledgeDocumentStatus::Indexing : KnowledgeDocumentStatus::Parsed;
    }

    /**
     * 文档归属的知识库。
     */
    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    /**
     * 文档所在的分组。
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(KnowledgeGroup::class, 'group_id');
    }

    /**
     * 上传文档的用户。
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id')->withTrashed();
    }

    /**
     * 文档上传时保留下来的原始文件对象。
     */
    public function originalFile(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable')
            ->where('purpose', AttachmentPurpose::KnowledgeDocument);
    }
}
