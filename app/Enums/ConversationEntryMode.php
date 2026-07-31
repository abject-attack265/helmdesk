<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 会话进入方式，描述访客如何进入接待流程。
 */
enum ConversationEntryMode: string implements LabeledEnum
{
    case Widget = 'widget';
    case Standalone = 'standalone';
    case Telegram = 'telegram';
    case WechatOfficialAccount = 'wechat_oa';

    public function label(): string
    {
        return match ($this) {
            self::Widget => __('conversation.entry_modes.widget'),
            self::Standalone => __('conversation.entry_modes.standalone'),
            self::Telegram => __('conversation.entry_modes.telegram'),
            self::WechatOfficialAccount => __('conversation.entry_modes.wechat_official_account'),
        };
    }
}
