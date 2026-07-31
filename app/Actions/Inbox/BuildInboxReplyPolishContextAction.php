<?php

namespace App\Actions\Inbox;

use App\Data\Inbox\InboxReplyPolishContextData;
use App\Data\Inbox\InboxReplyPolishMessageContextData;
use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 为收件箱 AI 回复助手组装轻量会话上下文。
 */
class BuildInboxReplyPolishContextAction
{
    use AsAction;

    private const int MAX_RECENT_MESSAGES = 30;

    private const int MAX_MESSAGE_LENGTH = 1000;

    private const int MAX_SUMMARY_LENGTH = 2000;

    /**
     * 读取最近文本消息、引用消息和会话摘要，供一次性帮写或改写使用。
     */
    public function handle(Conversation $conversation, ?string $quotedMessageId = null, ?string $teammateLocale = null): InboxReplyPolishContextData
    {
        $quotedMessage = $this->resolveQuotedMessage($conversation, $quotedMessageId);
        $recentMessages = ConversationMessage::query()

            ->where('conversation_id', $conversation->id)
            ->whereIn('role', [
                MessageRole::Visitor,
                MessageRole::Ai,
                MessageRole::Teammate,
            ])
            ->where('kind', MessageKind::Text)
            ->whereNotNull('content')
            ->whereNull('recalled_at')
            ->orderByDesc('seq_no')
            ->limit(self::MAX_RECENT_MESSAGES)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (ConversationMessage $message): InboxReplyPolishMessageContextData => $this->messageContext($message))
            ->all();

        return new InboxReplyPolishContextData(
            visitor_locale: $conversation->visitor_locale,
            teammate_locale: $this->trimNullable($teammateLocale, 20),
            conversation_subject: $this->trimNullable($conversation->subject, 200),
            conversation_summary: $this->trimNullable($conversation->summary, self::MAX_SUMMARY_LENGTH),
            quoted_message: $quotedMessage !== null ? $this->messageContext($quotedMessage) : null,
            recent_messages: $recentMessages,
        );
    }

    /**
     * 解析当前会话内仍可见的引用文本消息。
     */
    private function resolveQuotedMessage(Conversation $conversation, ?string $quotedMessageId): ?ConversationMessage
    {
        $resolvedQuotedMessageId = ConversationMessage::resolveQuotedMessageId(
            (string) $conversation->id,
            $quotedMessageId,
        );

        if ($resolvedQuotedMessageId === null) {
            return null;
        }

        return ConversationMessage::query()
            ->where('conversation_id', $conversation->id)
            ->whereKey($resolvedQuotedMessageId)
            ->where('kind', MessageKind::Text)
            ->whereNotNull('content')
            ->first();
    }

    /**
     * 把消息模型转换为润色上下文数据。
     */
    private function messageContext(ConversationMessage $message): InboxReplyPolishMessageContextData
    {
        return new InboxReplyPolishMessageContextData(
            role: $message->role->value,
            sender_name: (string) $message->sender_name,
            content: $this->trimNullable($message->content, self::MAX_MESSAGE_LENGTH) ?? '',
            occurred_at: $message->created_at?->toISOString(),
        );
    }

    /**
     * 清理可空文本并限制长度。
     */
    private function trimNullable(?string $value, int $limit): ?string
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        return Str::limit($trimmed, $limit, '');
    }
}
