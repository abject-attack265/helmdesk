<?php

namespace App\Models;

use App\Data\Reception\Config\PersonaConfigData;
use App\Data\Reception\Config\ReceptionStrategyConfigData;
use App\Data\Reception\Plan\ReceptionPlanCompiledConfigData;
use App\Enums\ReceptionPlanVersionStatus;
use Database\Factories\ReceptionPlanVersionFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $reception_plan_id 所属接待方案
 * @property int $version_number 方案内递增版本号
 * @property string|null $description 版本说明 / 变更备注
 * @property array $snapshot_config 发布时的方案配置快照（原样保存）
 * @property array $compiled_config 运行时编译后的配置（供渠道直接解析）
 * @property ReceptionPlanVersionStatus $status 版本状态：published / archived
 * @property Carbon|null $published_at 发布时间
 * @property string|null $published_by_user_id 发布操作的用户
 * @property mixed $use_factory
 * @property int|null $plans_count
 * @property int|null $published_bies_count
 * @property int|null $conversations_count
 * @property-read ReceptionPlan $plan
 * @property-read User|null $publishedBy
 * @property-read Collection|Conversation[] $conversations
 *
 * @method static \Database\Factories\ReceptionPlanVersionFactory<self> factory($count = null, $state = [])
 */
class ReceptionPlanVersion extends Model
{
    /**
     * 接待方案版本，发布时刻的不可变快照。
     * snapshot_config 保留草稿原貌用于后台展示与对比；compiled_config 是接待运行时实际加载的形态。
     * 本表不软删；归档走 status=archived，历史会话仍可通过 ID 解析回完整 compiled_config。
     */

    /** @use HasFactory<ReceptionPlanVersionFactory> */
    use HasFactory, HasUlids;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'snapshot_config' => 'array',
            'compiled_config' => 'array',
            'status' => ReceptionPlanVersionStatus::class,
            'version_number' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * 版本归属的接待方案；Plan 可能软删，但已发布版本仍需继续被历史会话解析回 compiled_config。
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(ReceptionPlan::class, 'reception_plan_id')->withTrashed();
    }

    /**
     * 发布此版本的用户；用户软删后版本历史仍要显示 "v3 published by alice"，审计完整性必需。
     */
    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id')->withTrashed();
    }

    /**
     * 锁定到本版本的所有会话；已建会话锁定创建时的版本，便于回放与对账。
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'reception_plan_version_id');
    }

    /**
     * 运行时编译产物的类型化访问；compiled_config 的消费方一律经由本方法，不直接读原始数组。
     */
    public function compiledConfig(): ReceptionPlanCompiledConfigData
    {
        return ReceptionPlanCompiledConfigData::fromArray(
            is_array($this->compiled_config) ? $this->compiled_config : [],
        );
    }

    /**
     * 版本快照中接待流程策略的类型化访问。
     */
    public function strategyConfig(): ReceptionStrategyConfigData
    {
        return ReceptionStrategyConfigData::fromArray($this->snapshotBlock('strategy_config'));
    }

    /**
     * 版本快照中人设配置的类型化访问。
     */
    public function personaConfig(): PersonaConfigData
    {
        return PersonaConfigData::fromArray($this->snapshotBlock('persona_config'));
    }

    /**
     * 取 snapshot_config 中的一个配置块；缺失说明发布侧写入有缺陷，尽早失败。
     *
     * @return array<string, mixed>
     */
    private function snapshotBlock(string $key): array
    {
        $block = is_array($this->snapshot_config) ? ($this->snapshot_config[$key] ?? null) : null;
        if (! is_array($block)) {
            throw new LogicException("Reception plan snapshot must contain {$key}.");
        }

        return $block;
    }
}
