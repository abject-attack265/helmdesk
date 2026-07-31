<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

enum InvitationStatus: string implements LabeledEnum
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('teammate.invitation.statuses.pending'),
            self::Accepted => __('teammate.invitation.statuses.accepted'),
            self::Expired => __('teammate.invitation.statuses.expired'),
        };
    }
}
