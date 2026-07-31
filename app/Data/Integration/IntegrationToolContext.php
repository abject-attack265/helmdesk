<?php

namespace App\Data\Integration;

use Spatie\LaravelData\Data;

/**
 * 业务系统工具调用使用的联系人和会话上下文。
 *
 * 上下文包含联系人外部 ID、主邮箱和当前会话 ID，不包含对话消息。
 * 没有联系人语境的 AI 助手使用空上下文。
 */
class IntegrationToolContext extends Data
{
    /**
     * @param  ?string  $contact_external_id  联系人外部 ID（无则 null）
     * @param  ?string  $conversation_id  当前会话 id（无则 null）
     * @param  ?string  $email  当前会话联系人主邮箱（无则 null），供业务系统按邮箱识别客户
     */
    public function __construct(
        public ?string $contact_external_id = null,
        public ?string $conversation_id = null,
        public ?string $email = null,
    ) {}

    /**
     * 构造不含联系人和会话信息的空上下文。
     */
    public static function none(): self
    {
        return new self;
    }
}
