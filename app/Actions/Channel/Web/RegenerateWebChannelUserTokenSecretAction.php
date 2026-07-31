<?php

namespace App\Actions\Channel\Web;

use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Models\Channel;
use App\Services\Channel\WebChannelResolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 重新生成网站渠道签名访客密钥。
 */
class RegenerateWebChannelUserTokenSecretAction
{
    use AsAction;

    /**
     * 注入网站渠道解析服务。
     */
    public function __construct(
        private readonly WebChannelResolutionService $resolution,
    ) {}

    /**
     * 立即生成新的签名访客密钥并保存到渠道设置，密钥已写入 DB，页面刷新后从 props 读取。
     */
    public function handle(Channel $channel): void
    {
        $secret = Str::random(64);

        /** @var ChannelWebSettingsData $currentSettings */
        $currentSettings = $channel->settings;

        DB::transaction(fn () => $channel->update([
            'settings' => $currentSettings->mergeWith([
                'user_token_secret' => $secret,
            ]),
        ]));
    }

    /**
     * 接收重置请求，完成后回到网站渠道详情页。
     */
    public function asController(Request $request, string $channel): RedirectResponse
    {

        $channelModel = $this->resolution->find($channel);
        $this->handle($channelModel);

        return redirect()->back(302, [], route('app.manage.channels.web.show', [
            'channel' => $channelModel->id,
        ]));
    }
}
