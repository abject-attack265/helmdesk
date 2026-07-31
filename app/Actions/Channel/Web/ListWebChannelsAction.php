<?php

namespace App\Actions\Channel\Web;

use App\Data\Channel\Web\ShowWebChannelListPagePropsData;
use App\Data\Channel\Web\WebChannelData;
use App\Data\SimplePaginationData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Services\Channel\WebChannelWidgetEntryIconResolver;
use App\Services\Reception\ChannelReceptionPlanVersionResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 查询网站渠道列表。
 */
class ListWebChannelsAction
{
    use AsAction;

    /**
     * 注入渠道部署版本状态解析器和入口图标地址解析器。
     */
    public function __construct(
        private ChannelReceptionPlanVersionResolver $planVersionResolver,
        private WebChannelWidgetEntryIconResolver $entryIconResolver,
    ) {}

    /**
     * 查询当前应用的网站渠道列表。
     */
    public function handle(int $page = 1, int $perPage = SimplePaginationData::DEFAULT_PER_PAGE): ShowWebChannelListPagePropsData
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 24));

        $paginator = Channel::query()
            ->where('type', ChannelType::Web)
            ->with(['receptionPlan'])
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        $channels = $paginator->getCollection();
        $entryIconUrls = $this->entryIconResolver->urlsForChannels($channels);

        return new ShowWebChannelListPagePropsData(
            channel_list: $channels
                ->map(fn (Channel $channel) => WebChannelData::fromModel(
                    $channel,
                    $this->planVersionResolver->resolveChannelStatus($channel),
                    $entryIconUrls,
                ))
                ->all(),
            channel_list_pagination: SimplePaginationData::fromPaginator($paginator),
        );
    }

    /**
     * 返回网站渠道列表页面。
     */
    public function asController(Request $request): Response
    {

        return Inertia::render('channel/web/List', $this->handle(
            page: (int) $request->query('page', 1),
        )->toArray());
    }
}
