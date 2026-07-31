<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string $reception_plan_id
 * @property string $knowledge_base_id
 */
class ReceptionPlanKnowledgeBase extends Pivot
{
    /**
     * 接待方案与知识库关联的 pivot 模型。
     * 该 pivot 表使用 ULID 主键，故需自定义 Pivot 模型以在 attach / sync 时生成主键。
     */
    use HasUlids;

    public $incrementing = false;

    protected $table = 'reception_plan_knowledge_bases';
}
