<?php

namespace App\Actions\Channel\WechatOfficialAccount;

use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/** 恢复回收站中的微信公众号渠道。 */
class RestoreWechatOfficialAccountChannelAction
{
    use AsAction;

    /** 恢复微信公众号渠道。 */
    public function handle(Channel $channel): void
    {
        $channel->restore();
    }

    /** 查找已删除渠道并执行恢复。 */
    public function asController(Request $request, string $channel): RedirectResponse
    {
        $channelModel = Channel::query()->withTrashed()->where('type', ChannelType::WechatOfficialAccount)->findOrFail($channel);
        $this->handle($channelModel);

        return redirect()->route('app.manage.channels.wechat-official-account.index');
    }
}
