<?php

namespace App\Actions\Channel\Telegram;

use App\Enums\ChannelType;
use App\Models\Channel;
use Lorisleiva\Actions\Concerns\AsAction;

/** 判断 Telegram 机器人是否可以绑定到指定渠道。 */
class IsTelegramBotAvailableAction
{
    use AsAction;

    /**
     * 检查机器人是否未被其他渠道占用，回收站渠道同样保留绑定。
     */
    #[\NoDiscard('必须检查结果才能阻止同一个 Telegram 机器人绑定多个渠道')]
    public function handle(Channel $channel, int $botId): bool
    {
        return ! Channel::query()
            ->withTrashed()
            ->where('type', ChannelType::Telegram)
            ->whereKeyNot($channel->id)
            ->where('settings->bot_id', $botId)
            ->exists();
    }
}
