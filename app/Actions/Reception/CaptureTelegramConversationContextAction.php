<?php

namespace App\Actions\Reception;

use App\Data\Conversation\ChannelContext\TelegramConversationChannelContextData;
use App\Models\Conversation;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 把 Telegram update payload 里的访客用户元数据落到会话渠道上下文。
 *
 * 每条入站消息都会刷新；新值缺失时保留已有快照，避免后续消息把先前采到的字段抹掉。
 */
class CaptureTelegramConversationContextAction
{
    use AsAction;

    private const int TEXT_MAX = 255;

    /**
     * 写入或合并会话的 Telegram 渠道上下文。
     *
     * @param  array<string, mixed>  $meta  tg_user_id/username/language_code/is_premium/is_bot/chat_type
     */
    public function handle(Conversation $conversation, array $meta): void
    {
        $existing = $conversation->channel_context instanceof TelegramConversationChannelContextData
            ? $conversation->channel_context
            : null;

        $conversation->channel_context = new TelegramConversationChannelContextData(
            tg_user_id: $this->text($meta['tg_user_id'] ?? null) ?? $existing?->tg_user_id,
            username: $this->text($meta['username'] ?? null) ?? $existing?->username,
            language_code: $this->text($meta['language_code'] ?? null, 35) ?? $existing?->language_code,
            is_premium: $this->bool($meta['is_premium'] ?? null) ?? $existing?->is_premium,
            is_bot: $this->bool($meta['is_bot'] ?? null) ?? $existing?->is_bot,
            chat_type: $this->text($meta['chat_type'] ?? null, 35) ?? $existing?->chat_type,
            captured_at: Carbon::now()->toIso8601String(),
        );
        $conversation->save();
    }

    /**
     * 清洗文本字段：仅接受非空字符串，去空白并按长度上限截断。
     */
    private function text(mixed $value, int $max = self::TEXT_MAX): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : mb_substr($trimmed, 0, $max);
    }

    /**
     * 仅在显式提供布尔值时采纳，否则返回 null 交给上层保留旧值。
     */
    private function bool(mixed $value): ?bool
    {
        return is_bool($value) ? $value : null;
    }
}
