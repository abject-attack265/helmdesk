<?php

namespace App\Actions\Channel\Web;

use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Data\Channel\Web\FormUpdateWebChannelAccessData;
use App\Models\Channel;
use App\Services\Channel\WebChannelEmbedHostGate;
use App\Services\Channel\WebChannelResolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 更新网站渠道接入方式：嵌入域名白名单与聊天链接附加 query。
 * 此表单是接入方式的权威来源，入口/设备与传参映射表单不代写这些字段，避免跨表单覆盖。
 */
class UpdateWebChannelAccessAction
{
    use AsAction;

    /**
     * 注入嵌入域名规范化服务与渠道解析服务。
     */
    public function __construct(
        private readonly WebChannelEmbedHostGate $embedHostGate,
        private readonly WebChannelResolutionService $resolution,
    ) {}

    /**
     * 合并保存嵌入域名白名单与聊天链接附加 query，其余设置保持不变。
     */
    public function handle(Channel $channel, FormUpdateWebChannelAccessData $data): void
    {
        /** @var ChannelWebSettingsData $currentSettings */
        $currentSettings = $channel->settings;

        $settings = $currentSettings->mergeWith([
            'allowed_embed_hosts' => $this->normalizeAllowedHosts($data->allowed_embed_hosts),
            'standalone_link_query' => $this->normalizeStandaloneLinkQuery($data->standalone_link_query),
        ]);

        DB::transaction(fn () => $channel->update([
            'settings' => $settings,
        ]));
    }

    /**
     * 接收接入方式表单并返回渠道详情页。
     */
    public function asController(Request $request, string $channel): RedirectResponse
    {

        $channelModel = $this->resolution->find($channel);

        $this->handle($channelModel, FormUpdateWebChannelAccessData::from($request));

        return redirect()->back(302, [], route('app.manage.channels.web.show', [
            'channel' => $channelModel->id,
        ]));
    }

    /**
     * 整理嵌入域名白名单：规范化、去重并保留首次出现顺序；全空回退为 null（不限制）。
     *
     * @param  array<int, mixed>|null  $rawHosts
     * @return list<string>|null
     */
    private function normalizeAllowedHosts(?array $rawHosts): ?array
    {
        if ($rawHosts === null) {
            return null;
        }

        $normalized = [];
        foreach ($rawHosts as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            $candidate = $this->embedHostGate->normalize($entry);
            if ($candidate === null) {
                continue;
            }

            $normalized[$candidate] = true;
        }

        return $normalized === [] ? null : array_keys($normalized);
    }

    /**
     * 规范化聊天链接附加 query：去掉首尾空白与起始 `?`；空串视为未设置。
     */
    private function normalizeStandaloneLinkQuery(?string $query): ?string
    {
        $value = ltrim(trim((string) $query), '?');

        return $value === '' ? null : $value;
    }
}
