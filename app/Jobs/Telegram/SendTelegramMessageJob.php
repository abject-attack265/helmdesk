<?php

namespace App\Jobs\Telegram;

use App\Actions\Reception\ResolveTelegramReceptionContextAction;
use App\Enums\ChannelType;
use App\Enums\IdentityType;
use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageKind;
use App\Enums\MessageOutboxStatus;
use App\Exceptions\TelegramApiException;
use App\Models\Attachment;
use App\Models\Channel;
use App\Models\ContactIdentity;
use App\Models\ConversationMessage;
use App\Models\MessageOutbox;
use App\Services\Telegram\TelegramBotApi;
use App\Services\Telegram\TelegramHtmlConverter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/** 发送 Telegram 出站消息并更新 Outbox。 */
class SendTelegramMessageJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [5, 30];

    public function __construct(public readonly string $messageId)
    {
        $this->queue = 'channel-outbound';
    }

    public function handle(TelegramBotApi $api, TelegramHtmlConverter $htmlConverter): void
    {
        $message = ConversationMessage::query()
            ->with(['conversation.channel', 'conversation.contact'])
            ->find($this->messageId);
        $outbox = MessageOutbox::query()
            ->where('conversation_message_id', $this->messageId)
            ->first();

        if ($message === null) {
            $outbox?->failIfUnsent('对应的会话消息不存在。');
            Log::warning('Telegram 出站任务找不到对应会话消息。', [
                'message_id' => $this->messageId,
                'outbox_id' => $outbox?->id,
            ]);

            return;
        }

        if ($outbox === null) {
            Log::warning('Telegram 出站任务缺少 Outbox。', [
                'message_id' => $this->messageId,
            ]);

            return;
        }

        if ($outbox->status === MessageOutboxStatus::Sent) {
            $this->syncMessageProjection($message, $outbox);

            return;
        }

        if ($outbox->status === MessageOutboxStatus::Failed) {
            $this->syncMessageProjection($message, $outbox);

            return;
        }

        if ($message->recalled_at !== null) {
            $outbox->cancelPending('消息已撤回，取消外部渠道投递。');
            $this->syncMessageProjection($message, $outbox);
            Log::info('Telegram 待发送消息已因撤回取消。', [
                'message_id' => $this->messageId,
                'outbox_id' => (string) $outbox->id,
            ]);

            return;
        }

        $messagePayload = $message->payload ?? [];
        $claimToken = $outbox->claimForSending();
        if ($claimToken === null) {
            $this->syncMessageProjection($message, $outbox);

            return;
        }

        Log::info('Telegram 出站 Outbox 已领取。', [
            'message_id' => $this->messageId,
            'outbox_id' => (string) $outbox->id,
            'channel_id' => (string) $outbox->channel_id,
            'attempt' => $outbox->attempts,
        ]);

        $isMedia = in_array($message->kind, [MessageKind::Image, MessageKind::File], true);
        if (! $isMedia && ! filled($message->content)) {
            $this->markClaimedFailed($message, $outbox, $claimToken, 'Telegram 出站消息缺少正文。');

            return;
        }

        $conversation = $message->conversation;
        $channel = $conversation?->channel;
        if ($channel === null || $channel->type !== ChannelType::Telegram) {
            $this->markClaimedFailed($message, $outbox, $claimToken, 'Telegram 出站消息找不到有效渠道凭证。');

            return;
        }

        $settings = $channel->telegramSettings();
        if (! filled($settings->bot_token)) {
            Log::warning('Telegram 出站消息缺少 Bot Token。', [
                'message_id' => $this->messageId,
                'channel_id' => (string) $channel->id,
            ]);
            $this->markClaimedFailed($message, $outbox, $claimToken, 'Telegram 出站消息缺少 Bot Token。');

            return;
        }

        $chatId = $this->resolveChatId((string) $conversation->contact_id, $channel->code);
        if ($chatId === null) {
            Log::warning('Telegram 出站消息找不到目标 chat_id。', [
                'message_id' => $this->messageId,
                'conversation_id' => (string) $conversation->id,
            ]);
            $this->markClaimedFailed($message, $outbox, $claimToken, 'Telegram 出站消息找不到目标 chat_id。');

            return;
        }

        try {
            $sent = $isMedia
                ? $this->sendMedia($api, $channel, $chatId, $message)
                : $api->sendMessage(
                    (string) $settings->bot_token,
                    $chatId,
                    $htmlConverter->convert((string) $message->content),
                    $this->resolveReplyToMessageId($message),
                    'HTML',
                );
        } catch (TelegramApiException $e) {
            $reason = $this->providerFailureReason($e);
            Log::warning('Telegram 出站消息发送失败。', [
                'message_id' => $this->messageId,
                'outbox_id' => (string) $outbox->id,
                'status_code' => $e->statusCode,
                'retryable' => $this->isRetryable($e),
                'reason' => $e->getMessage(),
            ]);

            if ($this->isRetryable($e)) {
                $outbox->releaseForRetry($claimToken, $reason, $this->retryDelay($outbox->attempts));
                throw $e;
            }

            $this->markClaimedFailed($message, $outbox, $claimToken, $reason);

            return;
        } catch (Throwable $e) {
            $outbox->releaseForRetry($claimToken, $e->getMessage(), $this->retryDelay($outbox->attempts));
            Log::warning('Telegram 出站任务异常，等待重试。', [
                'message_id' => $this->messageId,
                'outbox_id' => (string) $outbox->id,
                'exception' => $e::class,
                'reason' => $e->getMessage(),
            ]);
            throw $e;
        }

        $metadata = [
            'telegram' => [
                'message_id' => is_int($sent['message_id'] ?? null) ? $sent['message_id'] : null,
                'chat_id' => $chatId,
            ],
        ];
        if (! $outbox->markSentIfClaimed(
            $claimToken,
            $metadata,
            isset($sent['message_id']) ? (string) $sent['message_id'] : null,
        )) {
            Log::warning('Telegram 出站完成后已失去 Outbox 租约。', [
                'message_id' => $this->messageId,
                'outbox_id' => (string) $outbox->id,
            ]);
            $this->syncMessageProjection($message, $outbox);

            return;
        }

        $message->update([
            'delivery_status' => MessageDeliveryStatus::Sent,
            'payload' => array_merge($messagePayload, $metadata),
        ]);
        Log::info('Telegram 出站消息发送完成。', [
            'message_id' => $this->messageId,
            'outbox_id' => (string) $outbox->id,
            'channel_id' => (string) $channel->id,
            'message_kind' => $message->kind->value,
        ]);
    }

    /** 读取绑定附件并发送 Telegram 图片或文件。 */
    private function sendMedia(TelegramBotApi $api, Channel $channel, int $chatId, ConversationMessage $message): array
    {
        $attachment = Attachment::query()
            ->where('attachable_type', $message->getMorphClass())
            ->where('attachable_id', $message->getKey())
            ->first();
        if ($attachment === null) {
            throw new TelegramApiException('Telegram 出站媒体消息缺少附件', 0);
        }

        $contents = $attachment->filesystem()->get($attachment->object_key);
        if ($contents === null) {
            throw new TelegramApiException('Telegram 出站媒体附件读取失败', 0);
        }

        $token = (string) $channel->telegramSettings()->bot_token;
        $caption = filled($message->content) ? (string) $message->content : null;
        $replyTo = $this->resolveReplyToMessageId($message);

        return $message->kind === MessageKind::Image
            ? $api->sendPhoto($token, $chatId, $contents, $attachment->original_name, $caption, $replyTo)
            : $api->sendDocument($token, $chatId, $contents, $attachment->original_name, $caption, $replyTo);
    }

    private function resolveChatId(string $contactId, string $channelCode): ?int
    {
        $value = ContactIdentity::query()
            ->where('contact_id', $contactId)
            ->where('type', IdentityType::ChannelAccount)
            ->where('namespace', ResolveTelegramReceptionContextAction::identityNamespace($channelCode))
            ->value('value');

        return is_numeric($value) ? (int) $value : null;
    }

    private function resolveReplyToMessageId(ConversationMessage $message): ?int
    {
        if (! filled($message->quoted_message_id)) {
            return null;
        }

        $telegramMessageId = ConversationMessage::query()->find($message->quoted_message_id)?->payload['telegram']['message_id'] ?? null;

        return is_int($telegramMessageId) ? $telegramMessageId : null;
    }

    public function failed(Throwable $exception): void
    {
        $outbox = MessageOutbox::query()
            ->where('conversation_message_id', $this->messageId)
            ->first();
        $outbox?->failIfUnsent($exception->getMessage());

        $message = ConversationMessage::query()->find($this->messageId);
        if ($message !== null && $outbox !== null) {
            $this->syncMessageProjection($message, $outbox);
        }

        Log::warning('Telegram 出站任务已耗尽重试。', [
            'message_id' => $this->messageId,
            'outbox_id' => $outbox?->id,
            'reason' => $exception->getMessage(),
        ]);
    }

    private function markClaimedFailed(ConversationMessage $message, MessageOutbox $outbox, string $claimToken, string $reason): void
    {
        if ($outbox->markFailedIfClaimed($claimToken, $reason)) {
            $message->update(['delivery_status' => MessageDeliveryStatus::Failed]);

            return;
        }

        $this->syncMessageProjection($message, $outbox);
    }

    private function syncMessageProjection(ConversationMessage $message, MessageOutbox $outbox): void
    {
        $status = match ($outbox->status) {
            MessageOutboxStatus::Sent => MessageDeliveryStatus::Sent,
            MessageOutboxStatus::Failed => MessageDeliveryStatus::Failed,
            default => MessageDeliveryStatus::Sending,
        };

        if ($message->delivery_status !== $status) {
            $message->update(['delivery_status' => $status]);
        }
    }

    /** 按当前投递次数返回与队列一致的重试等待秒数。 */
    private function retryDelay(int $attempts): int
    {
        return $attempts <= 1 ? 5 : 30;
    }

    private function isRetryable(TelegramApiException $exception): bool
    {
        return $exception->statusCode === 0 || $exception->statusCode === 429 || $exception->statusCode >= 500;
    }

    private function providerFailureReason(TelegramApiException $exception): string
    {
        return $exception->statusCode === 0
            ? $exception->getMessage()
            : sprintf('[Telegram 错误码 %d] %s', $exception->statusCode, $exception->getMessage());
    }
}
