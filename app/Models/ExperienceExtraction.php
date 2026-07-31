<?php

namespace App\Models;

use App\Enums\ExperienceExtractionStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string|null $triggered_by_user_id
 * @property ExperienceExtractionStatus $status running / completed / failed
 * @property Carbon|null $scanned_from 所选会话中最早的关闭时间（展示用）
 * @property Carbon|null $scanned_until 所选会话中最晚的关闭时间（展示用），也是下次筛选的默认起点
 * @property int $conversation_count 本次实际送入提炼的会话数
 * @property int $candidate_count 本次产出的候选经验数
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $knowledge_base_id 绑定的问答知识库，采纳的候选经验落入该库
 * @property mixed $use_factory
 * @property int|null $knowledge_bases_count
 * @property int|null $triggered_bies_count
 * @property int|null $candidates_count
 * @property int|null $conversations_count
 * @property-read KnowledgeBase|null $knowledgeBase
 * @property-read User|null $triggeredBy
 * @property-read Collection|ExperienceCandidate[] $candidates
 * @property-read Collection|Conversation[] $conversations
 *
 * @method static \Database\Factories\ExperienceExtractionFactory<self> factory($count = null, $state = [])
 */
class ExperienceExtraction extends Model
{
    /**
     * 经验提炼运行模型。
     *
     * 每次运行绑定一个问答知识库（knowledgeBase() 关联）：管理员从该库的「经验提炼」入口
     * 按时间段筛选并勾选一批人工会话后触发，后台 Job 对所选会话（conversations() 关联）
     * 做 LLM 聚合提炼产出候选经验，采纳的候选直接落入绑定的问答库。
     * scanned_from / scanned_until 记录所选会话关闭时间的最早/最晚值，仅作展示。
     */
    use HasFactory, HasUlids;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExperienceExtractionStatus::class,
            'scanned_from' => 'datetime',
            'scanned_until' => 'datetime',
            'conversation_count' => 'integer',
            'candidate_count' => 'integer',
        ];
    }

    /**
     * 绑定的问答知识库：任务在该库下创建，采纳的候选经验直接落入该库。
     */
    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(KnowledgeBase::class);
    }

    /**
     * 触发本次运行的管理员。
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    /**
     * 本次运行产出的候选经验。
     */
    public function candidates(): HasMany
    {
        return $this->hasMany(ExperienceCandidate::class, 'extraction_id');
    }

    /**
     * 本次运行消费的会话清单（支撑「已提炼过」标记与溯源）。
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(
            Conversation::class,
            'experience_extraction_conversations',
            'extraction_id',
            'conversation_id',
        )->withPivot('created_at');
    }
}
