<?php

namespace App\Data\Tag;

use App\Enums\TagScope;
use App\Enums\TagSource;
use App\Models\Tag;
use Spatie\LaravelData\Data;

/**
 * 标签列表项数据。
 * 用于标签管理页组内标签 chip，以及接待页/联系人页的标签选择与展示。
 */
class ListTagItemData extends Data
{
    public function __construct(
        public string $id,
        public string $tag_group_id,
        public string $name,
        public ?string $color,
        public ?string $description,
        public TagSource $source,
        public string $source_label,
        public bool $is_locked,
        public int $contact_usage_count,
        public int $conversation_usage_count,
        public int $usage_count,
        public string $created_at,
        public string $updated_at,
        public ?string $deleted_at,
        // 所属标签组信息：仅在加载了 tagGroup 关系时填充（如回收站需展示标签维度）。
        public ?string $tag_group_name = null,
        public ?TagScope $scope = null,
        public ?string $scope_label = null,
    ) {}

    /**
     * 由标签模型构建列表项；usage_count 取与组维度对应的口径（会话组取会话用量，联系人组取联系人用量）。
     */
    public static function fromModel(
        Tag $tag,
        ?int $contactUsageCount = null,
        int $conversationUsageCount = 0,
    ): self {
        $contactUsageCount ??= (int) ($tag->contacts_count ?? 0);
        $conversationUsageCount = $conversationUsageCount ?: (int) ($tag->conversations_count ?? 0);
        $group = $tag->relationLoaded('tagGroup') ? $tag->tagGroup : null;

        return new self(
            id: $tag->id,
            tag_group_id: $tag->tag_group_id,
            name: $tag->name,
            color: $tag->color,
            description: $tag->description,
            source: $tag->source,
            source_label: $tag->source->label(),
            is_locked: $tag->is_locked,
            contact_usage_count: $contactUsageCount,
            conversation_usage_count: $conversationUsageCount,
            usage_count: $contactUsageCount + $conversationUsageCount,
            created_at: $tag->created_at?->toIso8601String() ?? '',
            updated_at: $tag->updated_at?->toIso8601String() ?? '',
            deleted_at: $tag->deleted_at?->toIso8601String(),
            tag_group_name: $group?->name,
            scope: $group?->scope,
            scope_label: $group?->scope->label(),
        );
    }
}
