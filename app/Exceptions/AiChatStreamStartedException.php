<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * 表示 AI 助手已经向前端发送流式片段，此后禁止切换模型继续拼接回答。
 */
class AiChatStreamStartedException extends RuntimeException
{
    /**
     * 保留中断前已广播并需要持久化的回答片段。
     *
     * @param  list<array<string, mixed>>  $segments
     */
    public function __construct(
        public readonly array $segments,
        public readonly string $content,
        public readonly int $tool_call_count,
        Throwable $previous,
    ) {
        parent::__construct('AI 助手流式输出开始后发生错误。', previous: $previous);
    }
}
