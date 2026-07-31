<?php

namespace App\Actions\Inbox;

use App\Data\CurrentUserContextData;
use App\Data\Inbox\FormRenewInboxConversationActivityData;
use App\Models\Conversation;
use App\Models\User;
use App\Services\Conversation\ConversationReplyRule;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 续期客服当前选中会话的活动状态。
 *
 * 活动页面按固定间隔续期；访客端在租约有效期间持续展示接待方正在输入。
 */
class RenewInboxConversationActivityAction
{
    use AsAction;

    /**
     * 注入实时通知和会话回复规则服务。
     */
    public function __construct(
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
        private readonly ConversationReplyRule $replyRule,
    ) {}

    /**
     * 校验会话范围与回复权限后广播页面级活动租约。
     */
    public function handle(
        User $user,
        string $conversationId,
        FormRenewInboxConversationActivityData $data,
    ): void {
        $conversation = Conversation::query()
            ->find($conversationId);

        if ($conversation === null) {
            return;
        }

        if ($data->active && ! $this->replyRule->canReply($conversation, $user)) {
            return;
        }

        $this->realtimeNotifier->teammateActivity(
            $conversation,
            $data->activity_id,
            $data->sequence,
            $data->active,
        );
    }

    /**
     * 解析当前应用客服与活动数据并固定返回 204。
     */
    public function asController(Request $request, string $conversationId): Response
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $user = User::query()->findOrFail($ctx->user_id);
        $data = FormRenewInboxConversationActivityData::from($request);

        $this->handle(
            user: $user,
            conversationId: $conversationId,
            data: $data,
        );

        return response()->noContent();
    }
}
