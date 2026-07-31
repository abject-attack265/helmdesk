<?php

namespace App\Actions\Channel\WechatOfficialAccount;

use App\Actions\Channel\EnsureMessageOutboxAction;
use App\Enums\ChannelType;
use App\Enums\MessageKind;
use App\Enums\MessageOutboxStatus;
use App\Enums\MessageRole;
use App\Jobs\WechatOfficialAccount\SendWechatOfficialAccountMessageJob;
use App\Models\ConversationMessage;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/** 派发微信公众号出站消息。 */
class DispatchWechatOfficialAccountOutboundMessageAction
{
    use AsAction;

    /** 为可投递消息创建 Outbox 并派发发送任务。 */
    public function handle(ConversationMessage $message): void
    {
        if (! in_array($message->role, [MessageRole::Ai, MessageRole::Teammate], true)) {
            return;
        }

        if ($message->recalled_at !== null) {
            return;
        }

        $channel = $message->conversation->channel;
        if ($channel->type !== ChannelType::WechatOfficialAccount) {
            return;
        }

        $isText = $message->kind === MessageKind::Text && filled($message->content);
        $isImage = $message->kind === MessageKind::Image;
        if (! $isText && ! $isImage) {
            if ($message->kind === MessageKind::File) {
                EnsureMessageOutboxAction::run($message, '微信公众号暂不支持发送文件，请改用文字或图片回复。');
                Log::warning('微信公众号文件出站消息已标记投递失败。', [
                    'message_id' => (string) $message->id,
                    'channel_id' => (string) $channel->id,
                    'message_kind' => $message->kind->value,
                ]);
            }

            return;
        }

        $outbox = EnsureMessageOutboxAction::run($message);
        if (in_array($outbox->status, [MessageOutboxStatus::Sent, MessageOutboxStatus::Failed], true)) {
            return;
        }

        SendWechatOfficialAccountMessageJob::dispatch((string) $message->id)->afterCommit();
    }
}
