<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 附件生命周期状态，用于控制展示、绑定和清理。
 */
enum AttachmentStatus: string implements LabeledEnum
{
    case Pending = 'pending';
    case Uploaded = 'uploaded';
    case Attached = 'attached';
    case Failed = 'failed';
    case Expired = 'expired';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('attachments.status.pending'),
            self::Uploaded => __('attachments.status.uploaded'),
            self::Attached => __('attachments.status.attached'),
            self::Failed => __('attachments.status.failed'),
            self::Expired => __('attachments.status.expired'),
            self::Deleted => __('attachments.status.deleted'),
        };
    }
}
