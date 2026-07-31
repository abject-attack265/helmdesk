<?php

namespace App\Actions\Channel;

use App\Actions\Channel\Telegram\DispatchTelegramOutboundMessageAction;
use App\Actions\Channel\WechatOfficialAccount\DispatchWechatOfficialAccountOutboundMessageAction;
use App\Enums\ChannelType;
use App\Models\ConversationMessage;
use Lorisleiva\Actions\Concerns\AsAction;

/** 按消息所属渠道分发出站消息。 */
class DispatchOutboundMessageAction
{
    use AsAction;

    public function handle(ConversationMessage $message): void
    {
        match ($message->conversation?->channel?->type) {
            ChannelType::Telegram => DispatchTelegramOutboundMessageAction::run($message),
            ChannelType::WechatOfficialAccount => DispatchWechatOfficialAccountOutboundMessageAction::run($message),
            default => null,
        };
    }
}
