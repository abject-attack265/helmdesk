<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $reception_plan_id
 * @property string $integration_id
 * @property array|null $tool_whitelist 工具名白名单，null=该集成全部已启用工具
 * @property int|null $plans_count
 * @property int|null $integrations_count
 * @property-read ReceptionPlan $plan
 * @property-read Integration $integration
 */
class ReceptionPlanIntegrationGrant extends Model
{
    /**
     * 接待方案对某个集成的工具授权。
     * 授权粒度到 integration 级：tool_whitelist 为 null 表示放行该集成当前全部已启用工具，
     * 非空时收窄为白名单内的工具名（运行时再与「当前 is_enabled 且未下线」的工具求交集）。
     */
    use HasUlids;

    protected $table = 'reception_plan_integration_grants';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tool_whitelist' => 'array',
        ];
    }

    /**
     * 授权所属的接待方案。
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(ReceptionPlan::class, 'reception_plan_id');
    }

    /**
     * 被授权的集成。
     */
    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }
}
