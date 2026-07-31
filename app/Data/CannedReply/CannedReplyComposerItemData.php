<?php

namespace App\Data\CannedReply;

use App\Models\CannedReply;
use Spatie\LaravelData\Data;

/**
 * 收件箱回复输入框中的快捷回复候选项数据。
 */
class CannedReplyComposerItemData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $shortcut,
        public string $content,
        public bool $is_personal,
        public int $usage_count,
        public ?string $last_used_at,
    ) {}

    /**
     * 从快捷回复模型构造候选项。
     */
    public static function fromModel(CannedReply $reply): self
    {
        return new self(
            id: (string) $reply->id,
            name: $reply->name,
            shortcut: $reply->shortcut,
            content: $reply->content,
            is_personal: $reply->user_id !== null,
            usage_count: (int) $reply->usage_count,
            last_used_at: $reply->last_used_at?->toIso8601String(),
        );
    }
}
