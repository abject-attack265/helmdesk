<?php

namespace App\Data\Invitation;

use App\Enums\InvitationStatus;
use App\Models\Invitation;
use Spatie\LaravelData\Data;

/**
 * 成员列表页面展示的待接受邀请及当前用户操作权限。
 */
class ListInvitationItemData extends Data
{
    /** 创建待接受邀请列表项。 */
    public function __construct(
        public string $id,
        public string $email,
        public ?string $nickname,
        public InvitationStatus $status,
        public string $status_label,
        public ?string $invited_by_name,
        public string $expires_at,
        public string $created_at,
        public bool $can_manage,
    ) {}

    /** 从邀请记录和操作能力构建列表项。 */
    public static function fromModel(Invitation $invitation, bool $canManage): self
    {
        $status = $invitation->status();

        return new self(
            id: (string) $invitation->id,
            email: $invitation->email,
            nickname: $invitation->nickname,
            status: $status,
            status_label: $status->label(),
            invited_by_name: $invitation->inviter?->name,
            expires_at: $invitation->expires_at->toIso8601String(),
            created_at: $invitation->created_at->toIso8601String(),
            can_manage: $canManage,
        );
    }
}
