<?php

namespace App\Services\Realtime;

class MercureTopics
{
    public static function receptionInbox(): string
    {
        return 'urn:helmdesk:reception:inbox';
    }

    public static function receptionConversationSelector(): string
    {
        return 'urn:helmdesk:reception:conversation:{conversationId}';
    }

    public static function receptionConversation(string $conversationId): string
    {
        return 'urn:helmdesk:reception:conversation:'.$conversationId;
    }

    public static function aiChat(string $roundId): string
    {
        return 'urn:helmdesk:ai-chat:'.$roundId;
    }

    public static function aiChatSelector(): string
    {
        return 'urn:helmdesk:ai-chat:{roundId}';
    }
}
