<?php

namespace App\Actions\Reception;

use App\Enums\ConversationStatus;
use App\Models\Channel;
use App\Models\Conversation;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 按网站访客身份查找当前可见的已有会话。
 */
class FindExistingWebVisitorConversationAction
{
    use AsAction;

    /**
     * 注入已有网站访客联系人查询。
     */
    public function __construct(
        private readonly FindExistingWebVisitorContactAction $findExistingVisitorContact,
    ) {}

    /**
     * 优先返回开放会话；没有开放会话时返回最近关闭的会话。
     */
    public function handle(
        Channel $channel,
        ?string $sessionToken,
        ?string $userToken,
        bool $acceptExpiredUserToken = false,
    ): ?Conversation {
        $contact = $this->findExistingVisitorContact->handle(
            $channel,
            $sessionToken,
            $userToken,
            $acceptExpiredUserToken,
        );
        if ($contact === null) {
            return null;
        }

        $query = Conversation::query()
            ->where('channel_id', $channel->id)
            ->where('contact_id', $contact->id);

        return (clone $query)
            ->where('status', ConversationStatus::Open)
            ->first()
            ?? (clone $query)
                ->where('status', ConversationStatus::Closed)
                ->orderByDesc('closed_at')
                ->orderByDesc('id')
                ->first();
    }
}
