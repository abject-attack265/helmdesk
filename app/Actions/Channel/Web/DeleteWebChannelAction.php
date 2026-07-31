<?php

namespace App\Actions\Channel\Web;

use App\Models\Channel;
use App\Services\Channel\WebChannelResolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 软删除应用中的网站渠道。
 */
class DeleteWebChannelAction
{
    use AsAction;

    /**
     * 注入渠道解析服务。
     */
    public function __construct(
        private readonly WebChannelResolutionService $resolution,
    ) {}

    /**
     * 将网站渠道移入回收站。
     */
    public function handle(Channel $channel): void
    {
        $channel->delete();
    }

    /**
     * 查找网站渠道并处理删除请求。
     */
    public function asController(Request $request, string $channel): RedirectResponse
    {
        $channelModel = $this->resolution->find($channel);

        $this->handle($channelModel);

        return redirect()->route('app.manage.channels.web.index');
    }
}
