<?php

namespace App\Services\AiRuntime;

use NeuronAI\Chat\History\HistoryTrimmer;
use NeuronAI\Chat\Messages\Message;

/**
 * 保留业务会话原始角色顺序的 NeuronAI 历史裁剪器。
 *
 * AI 欢迎语、连续访客消息、连续客服回复以及联系人历史 SYSTEM 消息都可以进入历史。
 * 父类继续负责 token 统计与上下文窗口裁剪。
 */
class LenientHistoryTrimmer extends HistoryTrimmer
{
    /**
     * 不做任何消息顺序校验。
     *
     * @param  Message[]  $messages
     */
    protected function validateAlternation(array $messages): void {}
}
