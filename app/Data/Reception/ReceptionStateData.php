<?php

namespace App\Data\Reception;

use Spatie\LaravelData\Data;

/**
 * 访客接待窗口状态；首条真实消息落库前不包含会话 ID 和会话状态。
 */
class ReceptionStateData extends Data
{
    /**
     * 创建访客接待窗口状态。
     */
    public function __construct(
        public string $session_token,
        public ?string $conversation_id,
        public ?string $status,
        public string $assistant_name,
        public ?string $assistant_avatar_url,
        /** @var ReceptionMessageData[] */
        public array $messages,
        public ReceptionActivityStateData $agent_activity,
        /** 是否允许访客提交会话评价。 */
        public bool $can_rate = false,
        /** 已提交的会话评价。 */
        public ?ReceptionRatingData $rating = null,
    ) {}
}
