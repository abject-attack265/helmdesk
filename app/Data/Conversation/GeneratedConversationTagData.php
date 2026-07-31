<?php

namespace App\Data\Conversation;

use Spatie\LaravelData\Data;

/**
 * AI 从受控词表中选出的单个会话标签建议。
 * GenerateConversationTagsAction 从受控词表选出的标签建议；tag_id 由 Action 再次校验在词表内。
 */
class GeneratedConversationTagData extends Data
{
    public function __construct(
        public string $name,
        public float $confidence,
        public ?string $reason,
        public ?string $tag_id = null,
    ) {}

    /**
     * 从单条标签建议创建。
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromPayload(array $payload): self
    {
        $reason = is_string($payload['reason'] ?? null) ? trim($payload['reason']) : null;

        return new self(
            name: trim((string) ($payload['name'] ?? '')),
            confidence: (float) ($payload['confidence'] ?? 0.0),
            reason: $reason !== '' ? $reason : null,
            tag_id: is_string($payload['tag_id'] ?? null) ? trim($payload['tag_id']) : null,
        );
    }
}
