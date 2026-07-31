<?php

namespace App\Actions\Channel\WechatOfficialAccount;

use App\Data\Channel\WechatOfficialAccount\ShowWechatOfficialAccountChannelListPagePropsData;
use App\Data\Channel\WechatOfficialAccount\WechatOfficialAccountData;
use App\Data\SimplePaginationData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Services\Reception\ChannelReceptionPlanVersionResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/** 展示微信公众号渠道列表。 */
class ListWechatOfficialAccountChannelsAction
{
    use AsAction;

    /** 创建渠道列表查询。 */
    public function __construct(
        private readonly ChannelReceptionPlanVersionResolver $planVersionResolver,
    ) {}

    /** 查询微信公众号渠道列表。 */
    public function handle(int $page = 1, int $perPage = SimplePaginationData::DEFAULT_PER_PAGE): ShowWechatOfficialAccountChannelListPagePropsData
    {
        $paginator = Channel::query()
            ->where('type', ChannelType::WechatOfficialAccount)
            ->with('receptionPlan')
            ->latest('created_at')
            ->orderByDesc('id')
            ->paginate(max(1, min($perPage, 24)), ['*'], 'page', max(1, $page));

        return new ShowWechatOfficialAccountChannelListPagePropsData(
            channel_list: $paginator->getCollection()->map(fn (Channel $channel) => WechatOfficialAccountData::fromModel(
                $channel,
                $this->planVersionResolver->resolveChannelStatus($channel),
            ))->all(),
            channel_list_pagination: SimplePaginationData::fromPaginator($paginator),
        );
    }

    /** 渲染微信公众号渠道列表页面。 */
    public function asController(Request $request): Response
    {
        return Inertia::render('channel/wechat-official-account/List', $this->handle((int) $request->query('page', 1))->toArray());
    }
}
