<?php

namespace App\Services\Search;

use App\Enums\MessageRole;
use App\Models\ConversationMessage;
use App\Models\User;

/**
 * 解析会话消息在当前客服视角下可参与搜索的文本。
 */
class ConversationMessageVisibleTextResolver
{
    /**
     * 返回当前客服可以搜索到的文本。
     *
     * @return list<string>
     */
    public function texts(ConversationMessage $message, ?User $viewer): array
    {
        $texts = $this->visitorTexts($message);

        if ($viewer !== null) {
            $targetLocale = match ($message->role) {
                MessageRole::Visitor, MessageRole::Teammate, MessageRole::Ai => $viewer->locale,
                default => null,
            };

            $translatedText = $message->payload['translations'][(string) $targetLocale]['text'] ?? null;
            if (is_string($translatedText) && $translatedText !== '') {
                $texts[] = $translatedText;
            }
        }

        return $texts;
    }

    /**
     * 返回访客侧正文文本。
     *
     * @return list<string>
     */
    private function visitorTexts(ConversationMessage $message): array
    {
        return is_string($message->content) && $message->content !== ''
            ? [$message->content]
            : [];
    }
}
