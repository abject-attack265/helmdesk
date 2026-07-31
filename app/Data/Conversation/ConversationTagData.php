<?php

namespace App\Data\Conversation;

use App\Enums\TagSource;
use App\Models\Tag;
use Spatie\LaravelData\Data;

/**
 * 会话标签展示数据。
 * 用于接待页摘要块（ConversationSummaryBlock）上展示某次会话的标签 chip：
 * 区分 AI/人工来源，AI 标签带置信度与判断依据。要求传入的 Tag 带 conversation_tag_assignments 透视字段。
 */
class ConversationTagData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $color,
        public TagSource $source,
        public string $source_label,
        public ?float $confidence,
        public ?string $reason,
    ) {}

    /**
     * 由带会话标签透视字段的标签模型构建展示数据。
     */
    public static function fromModel(Tag $tag): self
    {
        $pivot = $tag->getRelationValue('pivot');
        $source = TagSource::from((string) $pivot->source);
        $confidence = $pivot->confidence;

        return new self(
            id: $tag->id,
            name: $tag->name,
            color: $tag->color,
            source: $source,
            source_label: $source->label(),
            confidence: $confidence !== null ? (float) $confidence : null,
            reason: $pivot->reason,
        );
    }
}
