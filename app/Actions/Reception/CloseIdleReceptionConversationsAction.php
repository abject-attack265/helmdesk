<?php

namespace App\Actions\Reception;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 关闭超过接待方案「空闲自动结束」时长仍无新消息的开放会话（由定时命令驱动）。
 *
 * 不区分接待状态一律硬关单，访客再发消息会开新会话。排队会话的超时转 AI 由
 * reception:take-over-overdue-conversations 先行评估（调度顺序在前），走到这里
 * 说明 AI 接管未启用或不可用，关单即最终兜底。评价邀请由 CloseConversationAction 统一处理。
 */
class CloseIdleReceptionConversationsAction
{
    use AsAction;

    /**
     * 注入关闭会话 Action，复用关单事件/实时推送与关单后评价流程。
     */
    public function __construct(
        private readonly CloseConversationAction $closeConversationAction,
    ) {}

    /**
     * 扫描开放会话，按锁定方案版本的空闲配置关闭超时会话。
     *
     * @return array{closed: int} 本次关闭数
     */
    public function handle(): array
    {
        $closed = 0;

        Conversation::query()
            ->where('status', ConversationStatus::Open)
            ->whereNotNull('reception_plan_version_id')
            ->with(['receptionPlanVersion', 'channel'])
            ->chunkById(200, function (Collection $conversations) use (&$closed): void {
                foreach ($conversations as $conversation) {
                    if (! $this->isIdleBeyondAutoClose($conversation)) {
                        continue;
                    }

                    $this->closeConversationAction->handle($conversation);
                    $closed++;
                }
            });

        return ['closed' => $closed];
    }

    /**
     * 会话所属方案开启了空闲自动结束，且最后活跃时间已超过配置的空闲分钟数。
     */
    private function isIdleBeyondAutoClose(Conversation $conversation): bool
    {
        $strategy = $conversation->receptionPlanVersion->strategyConfig();

        if (! $strategy->auto_close_enabled) {
            return false;
        }

        $idleSince = $conversation->lastActivityAt();

        return $idleSince->copy()->addMinutes($strategy->auto_close_idle_minutes)->isPast();
    }
}
