<?php

namespace App\Data\CannedReply;

use App\Models\CannedReply;
use Spatie\LaravelData\Data;

/**
 * 快捷回复列表项数据。
 * 用于 resources/js/pages/cannedReplies/Index.vue 的表格行展示，包含归属、使用量与可操作标记。
 */
class ListCannedReplyItemData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $shortcut,
        public string $content,
        public bool $is_personal,
        public ?string $owner_user_id,
        public ?string $owner_user_name,
        public int $usage_count,
        public ?string $last_used_at,
        public string $created_at,
        public string $updated_at,
        public bool $can_edit,
        public bool $can_delete,
    ) {}

    public static function fromModel(CannedReply $reply, bool $canEdit, bool $canDelete): self
    {
        return new self(
            id: (string) $reply->id,
            name: $reply->name,
            shortcut: $reply->shortcut,
            content: $reply->content,
            is_personal: $reply->user_id !== null,
            owner_user_id: $reply->user_id !== null ? (string) $reply->user_id : null,
            owner_user_name: $reply->relationLoaded('owner') ? $reply->owner?->name : null,
            usage_count: (int) $reply->usage_count,
            last_used_at: $reply->last_used_at?->toIso8601String(),
            created_at: $reply->created_at?->toIso8601String() ?? '',
            updated_at: $reply->updated_at?->toIso8601String() ?? '',
            can_edit: $canEdit,
            can_delete: $canDelete,
        );
    }
}
