<?php

namespace App\Actions\Reception;

use App\Data\Reception\ReceptionStateData;
use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\MessageRole;
use App\Exceptions\BusinessException;
use App\Models\Channel;
use App\Models\ConversationMessage;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use App\Services\Reception\ReceptionSession;
use App\Services\Reception\ReceptionStateBuilder;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * 访客撤回自己发送过的消息。
 *
 * 撤回保留原内容供审计，仅记录撤回时间并由访客端渲染占位。
 */
class RecallVisitorMessageAction
{
    use AsAction;

    /**
     * 注入已有网站访客会话查询与实时通知器。
     */
    public function __construct(
        private readonly FindExistingWebVisitorConversationAction $findExistingVisitorConversation,
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
    ) {}

    /**
     * 撤回访客在当前会话内的指定消息并广播撤回事件。
     */
    public function handle(
        string $channelCode,
        ?string $sessionToken,
        string $messageId,
        ?string $userToken = null,
    ): ReceptionStateData {
        $channel = Channel::query()
            ->withTrashed()
            ->where('code', $channelCode)
            ->where('type', ChannelType::Web)
            ->first();
        if ($channel === null) {
            throw new NotFoundHttpException;
        }

        $resolvedToken = ReceptionSession::normalize($sessionToken) ?? ReceptionSession::generate();
        $conversation = $this->findExistingVisitorConversation->handle(
            $channel,
            $resolvedToken,
            $userToken,
        );
        if ($conversation === null) {
            throw new NotFoundHttpException;
        }
        if ($conversation->status !== ConversationStatus::Open) {
            throw new BusinessException(__('conversation.errors.already_closed'));
        }

        $message = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)

            ->whereKey($messageId)
            ->first();

        if ($message === null) {
            throw new NotFoundHttpException(__('conversation.errors.message_not_found'));
        }

        if ($message->role !== MessageRole::Visitor) {
            throw new BusinessException(__('conversation.errors.recall_not_owner'));
        }

        if ($message->isRecalled()) {
            throw new BusinessException(__('conversation.errors.recall_already_recalled'));
        }

        if (! $message->isWithinRecallWindow()) {
            throw new BusinessException(__('conversation.errors.recall_window_expired', [
                'minutes' => ConversationMessage::RECALL_WINDOW_MINUTES,
            ]));
        }

        $message->markRecalled($conversation);

        $conversation->refresh();
        $this->realtimeNotifier->conversationChanged(
            $conversation,
            'message_recalled',
            meta: ['message_id' => (string) $message->id],
        );

        return ReceptionStateBuilder::build($channel, $conversation, $resolvedToken);
    }
}
