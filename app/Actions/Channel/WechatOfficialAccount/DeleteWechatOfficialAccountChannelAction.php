<?php

namespace App\Actions\Channel\WechatOfficialAccount;

use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/** 将微信公众号渠道移入回收站。 */
class DeleteWechatOfficialAccountChannelAction
{
    use AsAction;

    /** 软删除微信公众号渠道。 */
    public function handle(Channel $channel): void
    {
        $channel->delete();
    }

    /** 查找渠道并执行软删除。 */
    public function asController(Request $request, string $channel): RedirectResponse
    {
        $channelModel = Channel::query()->where('type', ChannelType::WechatOfficialAccount)->findOrFail($channel);
        $this->handle($channelModel);

        return redirect()->route('app.manage.channels.wechat-official-account.index');
    }
}
