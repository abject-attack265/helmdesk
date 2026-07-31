<?php

namespace App\Models;

use Database\Factories\ReceptionPlanFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string $name
 * @property string|null $description
 * @property array|null $persona_config AI 人设配置：身份 / 语气等
 * @property string|null $global_instructions 全局系统指令（追加到 AI 提示词）
 * @property array $strategy_config 模型 / 回退策略配置
 * @property mixed $use_factory
 * @property int|null $versions_count
 * @property int|null $integration_grants_count
 * @property int|null $knowledge_bases_count
 * @property-read Collection|ReceptionPlanVersion[] $versions
 * @property-read Collection|ReceptionPlanIntegrationGrant[] $integrationGrants
 * @property-read Collection|KnowledgeBase[] $knowledgeBases
 *
 * @method static \Database\Factories\ReceptionPlanFactory<self> factory($count = null, $state = [])
 */
class ReceptionPlan extends Model
{
    /**
     * 接待方案草稿，承载 app 内 AI 接待的人设、全局指令、知识库与集成工具授权。
     * 发布时由 CompileReceptionPlanAction 生成不可变快照写入 ReceptionPlanVersion；
     * 草稿编辑不影响线上行为。
     */

    /** @use HasFactory<ReceptionPlanFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'persona_config' => 'array',
            'strategy_config' => 'array',
        ];
    }

    /**
     * Plan 的所有版本（含已归档）。版本表不软删，软删 Plan 后版本仍可被历史会话解析。
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ReceptionPlanVersion::class);
    }

    /**
     * Plan 对各集成的工具授权（integration 级 + 可选工具白名单）。
     */
    public function integrationGrants(): HasMany
    {
        return $this->hasMany(ReceptionPlanIntegrationGrant::class);
    }

    /**
     * Plan 关联的知识库（接待运行时可检索范围）。
     */
    public function knowledgeBases(): BelongsToMany
    {
        return $this->belongsToMany(KnowledgeBase::class, 'reception_plan_knowledge_bases')
            ->using(ReceptionPlanKnowledgeBase::class)
            ->withTimestamps();
    }
}
