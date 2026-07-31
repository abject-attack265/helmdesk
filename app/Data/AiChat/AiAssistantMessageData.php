<?php

namespace App\Data\AiChat;

use App\Enums\AiAssistantMessageRole;
use App\Enums\AiAssistantMessageStatus;
use App\Models\AiAssistantMessage;
use Spatie\LaravelData\Data;

/**
 * AI 助手面板恢复会话历史时使用的消息数据。
 */
class AiAssistantMessageData extends Data
{
    /**
     * 保存一条可恢复到前端消息列表的问答消息。
     *
     * @param  list<AiAssistantMessageSegmentData>  $segments
     * @param  list<string>  $attachment_ids
     * @param  list<string>  $image_urls
     */
    public function __construct(
        public string $id,
        public string $round_id,
        public AiAssistantMessageRole $role,
        public ?string $content,
        public array $segments,
        public array $attachment_ids,
        public array $image_urls,
        public AiAssistantMessageStatus $status,
    ) {}

    /**
     * 从持久化消息及已校验的附件地址创建前端数据。
     *
     * @param  list<string>  $image_urls
     */
    public static function fromModel(AiAssistantMessage $message, array $image_urls): self
    {
        return new self(
            id: $message->id,
            round_id: $message->round_id,
            role: $message->role,
            content: $message->content,
            segments: array_map(
                static fn (array $segment): AiAssistantMessageSegmentData => AiAssistantMessageSegmentData::from($segment),
                $message->segments ?? [],
            ),
            attachment_ids: $message->attachment_ids ?? [],
            image_urls: $image_urls,
            status: $message->status,
        );
    }
}
