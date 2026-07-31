<?php

namespace App\Services\Reception;

use App\Jobs\Reception\FlushReceptionBufferJob;
use App\Services\Realtime\ReceptionRealtimeNotifier;

/**
 * 将已落库的访客消息、输入状态和撤回信号接入接待轮次管道。
 */
class ReceptionPipelineDispatcher
{
    /**
     * 注入 debounce 聚合器、turn 抢占信号与实时通知器。
     */
    public function __construct(
        private readonly ReceptionDebouncer $debouncer,
        private readonly ReceptionPreemptionSignal $preemption,
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
    ) {}

    /**
     * 接入一条已落库的访客消息：缓冲聚合、抢占在跑的 turn，并按自适应窗口延迟派发 flush。
     *
     * 仅应在会话处于 AI 接待状态时调用（人工接管时不触发 AI turn）。
     *
     * @param  string  $messageId  该访客文本消息的 DB ID；为空表示无文本消息（纯图片/视频发送）
     * @param  list<string>  $mediaIds  本次发送携带的图片/视频消息 DB ID，作为多模态新消息一并喂给模型
     */
    public function enqueueVisitorMessage(string $conversationId, string $content, string $messageId = '', array $mediaIds = []): void
    {
        $window = $this->debouncer->acceptOnce($conversationId, $content, $messageId, $mediaIds);
        if ($window === null) {
            return;
        }

        $this->realtimeNotifier->aiDebounceStarted($conversationId);

        // 若该会话正有 turn 在飞行中，其上下文已缺失本条消息：请求抢占让旧轮丢弃产物，
        // 本消息触发的下一轮会带着合并后的历史一并回答，避免 AI 对同一问题连发多条回复。
        $this->preemption->requestPreemption($conversationId);

        FlushReceptionBufferJob::dispatch($conversationId)
            ->delay(now()->addMilliseconds($window));
    }

    /**
     * 记录一次访客「正在输入」信号，推迟本会话的 flush。
     */
    public function noteTyping(string $conversationId): void
    {
        $this->debouncer->noteTyping($conversationId);
    }

    /**
     * 处理访客撤回：从尚未 flush 的缓冲中移除该消息。
     */
    public function notifyMessageRecalled(string $conversationId, string $messageId): void
    {
        $this->debouncer->acceptRecall($conversationId, $messageId);
    }
}
