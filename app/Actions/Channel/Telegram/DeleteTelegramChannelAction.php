<?php

namespace App\Actions\Channel\Telegram;

use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/** 暂停 Telegram 渠道并保留 webhook，以便恢复后继续接收消息。 */
class DeleteTelegramChannelAction
{
    use AsAction;

    /** 将渠道移入回收站。 */
    public function handle(Channel $channel): void
    {
        $channel->delete();
    }

    /** 处理删除请求并返回渠道列表。 */
    public function asController(Request $request, string $channel): RedirectResponse
    {
        $channelModel = Channel::query()
            ->where('type', ChannelType::Telegram)
            ->findOrFail($channel);

        $this->handle($channelModel);

        return redirect()->route('app.manage.channels.telegram.index');
    }
}
