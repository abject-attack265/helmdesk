<?php

namespace App\Actions\Channel\Telegram;

use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 从回收站恢复 Telegram 渠道。
 *
 * 仅恢复软删除状态，不重新注册 webhook：暂停时未撤销 Telegram 侧 webhook，
 * 本地注册标记也保留，恢复后即可继续接收消息（如确需重连可在详情页手动重注册）。
 */
class RestoreTelegramChannelAction
{
    use AsAction;

    /**
     * 恢复软删除的渠道（不触碰 Telegram 侧 webhook）。
     */
    public function handle(Channel $channel): void
    {
        $channel->restore();
    }

    /**
     * 接收恢复请求并返回列表页。
     */
    public function asController(Request $request, string $channel): RedirectResponse
    {

        $channelModel = Channel::query()
            ->withTrashed()

            ->where('type', ChannelType::Telegram)
            ->findOrFail($channel);

        $this->handle($channelModel);

        return redirect()->route('app.manage.channels.telegram.index');
    }
}
