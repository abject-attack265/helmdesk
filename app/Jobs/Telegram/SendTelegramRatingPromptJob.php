<?php

namespace App\Jobs\Telegram;

use App\Actions\Reception\ResolveTelegramReceptionContextAction;
use App\Enums\ChannelType;
use App\Enums\IdentityType;
use App\Exceptions\TelegramApiException;
use App\Models\ContactIdentity;
use App\Models\Conversation;
use App\Services\LocalePreference;
use App\Services\Telegram\TelegramBotApi;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * 会话关闭后向 Telegram 访客发送满意度评价提示（👍/👎 inline 按钮）。
 *
 * 按钮 callback_data 编码为 `csat:{score}:{conversationId}`，由 webhook 的 callback_query 分支落库。
 * best-effort：解析不到 chat_id 或发送失败只记日志，不影响关单。
 */
class SendTelegramRatingPromptJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $conversationId)
    {
        $this->queue = 'channel-outbound';
    }

    /**
     * 解析目标 chat 并发送评价提示按钮。
     */
    public function handle(TelegramBotApi $api): void
    {
        $conversation = Conversation::query()->with('channel')->find($this->conversationId);
        $channel = $conversation?->channel;
        if ($conversation === null || $channel === null || $channel->type !== ChannelType::Telegram) {
            return;
        }

        $botToken = (string) $channel->telegramSettings()->bot_token;
        if ($botToken === '') {
            Log::warning('Telegram 评价提示缺少 Bot Token。', [
                'conversation_id' => $this->conversationId,
                'channel_id' => (string) $channel->id,
            ]);

            return;
        }

        if ($conversation->contact_id === null) {
            return;
        }

        $chatId = $this->resolveChatId(
            (string) $conversation->contact_id,
            $channel->code,
        );
        if ($chatId === null) {
            return;
        }

        // 按访客界面语言解析提示文案，而非后台默认 locale。
        $locale = LocalePreference::normalizeLaravel($conversation->visitor_locale);
        $keyboard = [[
            ['text' => (string) __('conversation.rating.telegram.satisfied', [], $locale), 'callback_data' => 'csat:positive:'.$conversation->id],
            ['text' => (string) __('conversation.rating.telegram.unsatisfied', [], $locale), 'callback_data' => 'csat:negative:'.$conversation->id],
        ]];

        try {
            $api->sendInlineKeyboard($botToken, $chatId, (string) __('conversation.rating.telegram.prompt', [], $locale), $keyboard);
        } catch (TelegramApiException $exception) {
            Log::warning('Telegram 评价提示发送失败（best-effort）', [
                'conversation_id' => $this->conversationId,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * 由联系人 Telegram 身份解析目标 chat_id（私聊场景 chat_id 即用户 id）。
     */
    private function resolveChatId(string $contactId, string $channelCode): ?int
    {
        $value = ContactIdentity::query()
            ->where('contact_id', $contactId)
            ->where('type', IdentityType::ChannelAccount)
            ->where('namespace', ResolveTelegramReceptionContextAction::identityNamespace($channelCode))
            ->value('value');

        return is_numeric($value) ? (int) $value : null;
    }
}
