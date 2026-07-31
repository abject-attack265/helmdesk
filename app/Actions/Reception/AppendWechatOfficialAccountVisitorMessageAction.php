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
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/** 把微信公众号文本消息追加到接待会话。 */
class AppendWechatOfficialAccountVisitorMessageAction
{
    use AsAction;

    /** 创建微信公众号访客消息写入流程。 */
    public function __construct(
        private readonly ResolveWechatOfficialAccountReceptionContextAction $resolveContext,
        private readonly ReceptionRealtimeNotifier $realtimeNotifier,
        private readonly CaptureWechatOfficialAccountConversationContextAction $captureContext,
    ) {}

    /**
     * 幂等写入微信公众号访客文本并更新会话状态。
     *
     * @return array{conversation: Conversation, message: ConversationMessage}
     */
    public function handle(
        string $channelCode,
        string $openid,
        string $text,
        string $wechatMessageId,
        ?string $displayName = null,
        ?string $language = null,
    ): array {
        $openid = trim($openid);
        $text = trim($text);
        $wechatMessageId = trim($wechatMessageId);

        if ($openid === '') {
            throw ValidationException::withMessages(['openid' => '微信公众号消息缺少访客 OpenID。']);
        }

        if ($text === '') {
            throw ValidationException::withMessages(['content' => __('conversation.errors.empty_message')]);
        }

        if ($wechatMessageId === '') {
            throw ValidationException::withMessages(['message_id' => '微信公众号消息缺少 MsgId。']);
        }

        $context = $this->resolveContext->handle($channelCode, $openid, $displayName);
        /** @var Conversation $conversation */
        $conversation = $context['conversation'];
        $conversation->loadMissing('contact');

        $this->captureContext->handle($conversation, $openid, $displayName, $language);

        $visitorSenderName = (string) ($conversation->contact?->name ?? $displayName ?? '微信公众号访客');
        $clientMsgId = 'wxoa_'.$channelCode.'_'.$wechatMessageId;

        $existingMessage = ConversationMessage::findByClientMsgId($conversation->id, $clientMsgId);
        if ($existingMessage !== null) {
            Log::info('微信公众号重复消息已跳过写入。', [
                'conversation_id' => (string) $conversation->id,
                'wechat_message_id' => $wechatMessageId,
            ]);
            $conversation->refresh();
            $existingMessage->refresh();

            return [
                'conversation' => $conversation,
                'message' => $existingMessage,
            ];
        }

        try {
            $message = DB::transaction(function () use ($conversation, $text, $visitorSenderName, $clientMsgId, $wechatMessageId): ConversationMessage {
                $message = ConversationMessage::query()->create([
                    'conversation_id' => $conversation->id,
                    'role' => MessageRole::Visitor,
                    'sender_name' => $visitorSenderName,
                    'kind' => MessageKind::Text,
                    'content' => $text,
                    'payload' => [
                        'wechat_oa' => [
                            'message_id' => $wechatMessageId,
                        ],
                    ],
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
            $existingMessage = ConversationMessage::findByClientMsgId($conversation->id, $clientMsgId);
            if ($existingMessage === null) {
                throw $exception;
            }

            Log::info('微信公众号并发重复消息已跳过写入。', [
                'conversation_id' => (string) $conversation->id,
                'wechat_message_id' => $wechatMessageId,
            ]);
            $conversation->refresh();
            $existingMessage->refresh();

            return [
                'conversation' => $conversation,
                'message' => $existingMessage,
            ];
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
