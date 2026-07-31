<?php

namespace App\Actions\Channel\Telegram;

use App\Actions\Channel\EnsureMessageOutboxAction;
use App\Enums\ChannelType;
use App\Enums\MessageKind;
use App\Enums\MessageOutboxStatus;
use App\Enums\MessageRole;
use App\Jobs\Telegram\SendTelegramMessageJob;
use App\Models\ConversationMessage;
use Lorisleiva\Actions\Concerns\AsAction;

/** 派发 Telegram 出站消息。 */
class DispatchTelegramOutboundMessageAction
{
    use AsAction;

    /** 出站消息满足条件时派发 Telegram 发送任务。 */
    public function handle(ConversationMessage $message): void
    {
        if (! in_array($message->role, [MessageRole::Ai, MessageRole::Teammate], true)) {
            return;
        }

        if ($message->recalled_at !== null) {
            return;
        }

        $isText = $message->kind === MessageKind::Text && filled($message->content);
        $isMedia = in_array($message->kind, [MessageKind::Image, MessageKind::File], true);
        if (! $isText && ! $isMedia) {
            return;
        }

        $channel = $message->conversation?->channel;
        if ($channel?->type !== ChannelType::Telegram) {
            return;
        }

        $outbox = EnsureMessageOutboxAction::run($message);
        if (in_array($outbox->status, [MessageOutboxStatus::Sent, MessageOutboxStatus::Failed], true)) {
            return;
        }

        SendTelegramMessageJob::dispatch((string) $message->id)->afterCommit();
    }
}
