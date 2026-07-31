<?php

namespace App\Data\Conversation;

use Spatie\LaravelData\Data;

/**
 * 被引用消息的轻量快照，用于时间线气泡和 composer 引用条展示。
 */
class QuotedMessageData extends Data
{
    public function __construct(
        public string $id,
        public string $role,
        public string $kind,
        public string $sender_name,
        public string $preview,
        public ?string $content,
        /** @var list<array<string, mixed>> */
        public array $attachments,
        public ?string $recalled_at,
    ) {}
}
