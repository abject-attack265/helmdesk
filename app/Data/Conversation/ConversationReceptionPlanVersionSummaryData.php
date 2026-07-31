<?php

namespace App\Data\Conversation;

use App\Enums\ReceptionPlanVersionStatus;
use App\Models\ReceptionPlanVersion;
use Spatie\LaravelData\Data;

/**
 * 会话锁定的接待方案版本摘要。
 * 由会话详情 / Inbox 详情下发，前端可以直接展示「本次接待使用：方案名 vN」。
 */
class ConversationReceptionPlanVersionSummaryData extends Data
{
    /**
     * 创建会话级 PlanVersion 摘要。
     */
    public function __construct(
        public string $id,
        public string $plan_id,
        public string $plan_name,
        public int $version_number,
        public ReceptionPlanVersionStatus $status,
        public string $status_label,
        public ?string $description,
        public ?string $persona_display_name,
    ) {}

    /**
     * 从模型映射；版本不存在时返回 null。
     */
    public static function fromModelOrNull(?ReceptionPlanVersion $version): ?self
    {
        if ($version === null) {
            return null;
        }

        $plan = $version->relationLoaded('plan') ? $version->plan : $version->plan()->first();
        $displayName = $version->personaConfig()->display_name;

        return new self(
            id: (string) $version->id,
            plan_id: (string) $version->reception_plan_id,
            plan_name: (string) ($plan?->name ?? ''),
            version_number: (int) $version->version_number,
            status: $version->status,
            status_label: $version->status->label(),
            description: filled($version->description) ? (string) $version->description : null,
            persona_display_name: filled($displayName) ? $displayName : null,
        );
    }
}
