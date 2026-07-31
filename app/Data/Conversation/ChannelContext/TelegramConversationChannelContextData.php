<?php

namespace App\Data\Conversation\ChannelContext;

/**
 * Telegram 渠道会话上下文：来自 update payload 的访客用户元数据。
 *
 * 入站时由 ReceiveTelegramUpdate 从 message.from / chat 提取，
 * 展示在坐席收件箱右侧的渠道上下文块。
 */
class TelegramConversationChannelContextData extends ConversationChannelContextData
{
    public function __construct(
        public string $channel_type = 'telegram',
        public ?string $tg_user_id = null,
        public ?string $username = null,
        public ?string $language_code = null,
        public ?bool $is_premium = null,
        public ?bool $is_bot = null,
        public ?string $chat_type = null,
        public ?string $captured_at = null,
    ) {}
}
