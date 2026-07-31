<?php

namespace App\Data\Inbox;

use Spatie\LaravelData\Data;

/**
 * 收件箱 AI 回复助手返回给前端的结果数据。
 */
class InboxReplyPolishResultData extends Data
{
    /**
     * 保存模型生成的多条候选回复。
     *
     * @param  InboxReplyPolishCandidateData[]  $candidates
     */
    public function __construct(
        public array $candidates,
    ) {}
}
