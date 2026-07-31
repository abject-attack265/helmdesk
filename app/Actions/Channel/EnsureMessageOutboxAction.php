<?php

namespace App\Actions\Channel;

use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageOutboxStatus;
use App\Models\ConversationMessage;
use App\Models\MessageOutbox;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/** 创建外部渠道消息投递 Outbox。 */
class EnsureMessageOutboxAction
{
    use AsAction;

    /** 为外部渠道消息创建 Outbox，并同步消息投递状态。 */
    public function handle(ConversationMessage $message, ?string $failureReason = null): MessageOutbox
    {
        $channel = $message->conversation->channel;
        $outbox = MessageOutbox::query()->firstOrCreate(
            ['conversation_message_id' => $message->id],
            [
                'channel_id' => $channel->id,
                'channel_type' => $channel->type,
                'status' => MessageOutboxStatus::Pending,
                // 发送 Job 直接领取；对账器在 60 秒后接管未领取记录。
                'available_at' => now()->addSeconds(60),
            ],
        );

        if ($failureReason !== null) {
            $outbox->failIfUnsent($failureReason);
        }

        $status = match ($outbox->status) {
            MessageOutboxStatus::Sent => MessageDeliveryStatus::Sent,
            MessageOutboxStatus::Failed => MessageDeliveryStatus::Failed,
            default => MessageDeliveryStatus::Sending,
        };
        $this->syncMessageStatus($message, $status);

        if ($outbox->wasRecentlyCreated) {
            Log::info('外部渠道消息 Outbox 已创建。', [
                'outbox_id' => (string) $outbox->id,
                'message_id' => (string) $message->id,
                'channel_type' => $outbox->channel_type->value,
            ]);
        }

        return $outbox;
    }

    /** 同步会话消息的投递状态投影。 */
    private function syncMessageStatus(ConversationMessage $message, MessageDeliveryStatus $status): void
    {
        if ($message->delivery_status !== $status) {
            $message->update(['delivery_status' => $status]);
        }
    }
}
