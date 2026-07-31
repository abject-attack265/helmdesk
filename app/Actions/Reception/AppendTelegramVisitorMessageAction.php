<?php

namespace App\Actions\Reception;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/** 保存 Telegram 入站文本并更新接待会话。 */
class AppendTelegramVisitorMessageAction
{
    use AsAction;

    /** Telegram 单条文本上限即 4096 字符。 */
    public const int MAX_CONTENT_LENGTH = 4096;

    /** 创建 Telegram 文本写入流程。 */
    public function __construct(
        private readonly ResolveTelegramReceptionContextAction $resolveTelegramReceptionContextAction,
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
        private readonly CaptureTelegramConversationContextAction $captureTelegramConversationContextAction,
    ) {}

    /**
     * 追加访客文本并保存 Telegram 用户上下文。
     *
     * @return array{conversation: Conversation, message: ConversationMessage}
     */
    public function handle(
        string $channelCode,
        string $telegramUserId,
        ?string $displayName,
        string $text,
        int $telegramMessageId,
        int $telegramChatId,
        ?string $username = null,
        ?string $languageCode = null,
        ?bool $isPremium = null,
        ?bool $isBot = null,
        ?string $chatType = null,
    ): array {
        $text = trim($text);

        if ($text === '') {
            throw ValidationException::withMessages(['content' => __('conversation.errors.empty_message')]);
        }
        $text = Str::limit($text, self::MAX_CONTENT_LENGTH, '');

        $context = $this->resolveTelegramReceptionContextAction->handle($channelCode, $telegramUserId, $displayName);
        /** @var Conversation $conversation */
        $conversation = $context['conversation'];
        $conversation->loadMissing('contact');

        $this->captureTelegramConversationContextAction->handle($conversation, [
            'tg_user_id' => $telegramUserId,
            'username' => $username,
            'language_code' => $languageCode,
            'is_premium' => $isPremium,
            'is_bot' => $isBot,
            'chat_type' => $chatType,
        ]);
        $visitorSenderName = (string) ($conversation->contact?->name ?? $displayName ?? 'Telegram');

        $clientMsgId = 'tg_'.$telegramMessageId;
        $existing = ConversationMessage::findByClientMsgId($conversation->id, $clientMsgId);
        if ($existing !== null) {
            return ['conversation' => $conversation->refresh(), 'message' => $existing];
        }

        try {
            $message = DB::transaction(function () use ($conversation, $text, $visitorSenderName, $clientMsgId, $telegramMessageId, $telegramChatId): ConversationMessage {
                $message = ConversationMessage::query()->create([
                    'conversation_id' => $conversation->id,
                    'role' => MessageRole::Visitor,
                    'sender_name' => $visitorSenderName,
                    'kind' => MessageKind::Text,
                    'content' => $text,
                    'payload' => ['telegram' => ['message_id' => $telegramMessageId, 'chat_id' => $telegramChatId]],
                    'client_msg_id' => $clientMsgId,
                ]);

                $conversation->update([
                    'last_message_at' => now(),
                    'last_message_preview' => Conversation::messagePreview($text),
                    'waiting_for_visitor_reply' => false,
                    'unread_agent_message_count' => 0,
                ]);

                Conversation::query()
                    ->whereKey($conversation->id)
                    ->increment('unread_visitor_message_count');

                return $message;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = ConversationMessage::findByClientMsgId($conversation->id, $clientMsgId);
            if ($existing === null) {
                throw $exception;
            }
            Log::info('Telegram 并发重复文本消息已跳过写入。', [
                'conversation_id' => (string) $conversation->id,
                'telegram_message_id' => $telegramMessageId,
            ]);

            return ['conversation' => $conversation->refresh(), 'message' => $existing];
        }

        $conversation->refresh();

        $this->realtimeNotifier->conversationChanged(
            $conversation,
            'visitor_message_created',
            meta: $message->realtimeMeta(),
        );

        return ['conversation' => $conversation, 'message' => $message];
    }
}
