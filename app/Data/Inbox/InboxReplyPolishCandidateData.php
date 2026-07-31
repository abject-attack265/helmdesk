<?php

namespace App\Data\Inbox;

use Spatie\LaravelData\Data;

/**
 * 收件箱 AI 回复助手返回给前端的一条候选回复。
 */
class InboxReplyPolishCandidateData extends Data
{
    /**
     * 保存候选编号和可填入输入框的文本。
     */
    public function __construct(
        public string $id,
        public string $content,
    ) {}

    /**
     * 根据模型返回顺序创建稳定候选编号。
     */
    public static function fromContent(int $index, string $content): self
    {
        return new self(
            id: 'candidate-'.($index + 1),
            content: $content,
        );
    }
}
