<?php

namespace App\Actions\Reception;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/** 更新 Telegram 访客文本并同步会话预览。 */
class UpdateTelegramVisitorMessageAction
{
    use AsAction;

    /** 创建 Telegram 文本编辑流程。 */
    public function __construct(
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
    ) {}

    /**
     * 更新 Telegram 访客文本并返回对应会话。
     *
     * @return array{conversation: ?Conversation, message: ?ConversationMessage}
     */
    public function handle(Channel $channel, int $telegramMessageId, string $newText): array
    {
        $newText = Str::limit(trim($newText), AppendTelegramVisitorMessageAction::MAX_CONTENT_LENGTH, '');
        if ($newText === '') {
            return ['conversation' => null, 'message' => null];
        }

        $message = ConversationMessage::query()
            ->where('client_msg_id', 'tg_'.$telegramMessageId)
            ->where('role', MessageRole::Visitor)
            ->where('kind', MessageKind::Text)
            ->whereHas('conversation', fn ($query) => $query->where('channel_id', $channel->id))
            ->first();

        if ($message === null || $message->isRecalled()) {
            return ['conversation' => null, 'message' => null];
        }

        /** @var Conversation $conversation */
        $conversation = $message->conversation()->first();
        if ((string) $message->content === $newText) {
            return ['conversation' => $conversation, 'message' => $message];
        }

        $payload = $message->payload ?? [];
        $payload['edited_at'] = now()->toIso8601String();
        $message->update([
            'content' => $newText,
            'payload' => $payload,
        ]);

        $latestId = ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->orderByDesc('seq_no')
            ->value('id');
        if ($latestId === $message->id) {
            $conversation->update(['last_message_preview' => Conversation::messagePreview($newText)]);
        }

        $this->realtimeNotifier->conversationChanged(
            $conversation,
            'visitor_message_updated',
            meta: $message->realtimeMeta(),
        );

        return ['conversation' => $conversation->refresh(), 'message' => $message];
    }
}
