<?php

namespace App\Actions\Reception;

use App\Data\Conversation\ChannelContext\WechatOfficialAccountConversationChannelContextData;
use App\Models\Conversation;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

/** 保存微信公众号回调携带的访客上下文快照。 */
class CaptureWechatOfficialAccountConversationContextAction
{
    use AsAction;

    private const int TEXT_MAX = 255;

    /** 更新会话中的微信公众号访客上下文。 */
    public function handle(
        Conversation $conversation,
        string $openid,
        ?string $nickname = null,
        ?string $language = null,
    ): void {
        $existing = $conversation->channel_context instanceof WechatOfficialAccountConversationChannelContextData
            ? $conversation->channel_context
            : null;

        $conversation->channel_context = new WechatOfficialAccountConversationChannelContextData(
            openid: mb_substr(trim($openid), 0, self::TEXT_MAX),
            nickname: $this->optionalText($nickname) ?? $existing?->nickname,
            language: $this->optionalText($language, 35) ?? $existing?->language,
            captured_at: Carbon::now()->toIso8601String(),
        );
        $conversation->save();
    }

    /** 规范化第三方可选文本字段。 */
    private function optionalText(?string $value, int $max = self::TEXT_MAX): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
