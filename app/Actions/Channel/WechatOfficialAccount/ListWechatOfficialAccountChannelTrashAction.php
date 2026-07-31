<?php

namespace App\Actions\Channel\WechatOfficialAccount;

use App\Data\Channel\WechatOfficialAccount\ShowWechatOfficialAccountChannelTrashPagePropsData;
use App\Data\Channel\WechatOfficialAccount\WechatOfficialAccountData;
use App\Data\SimplePaginationData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Services\Reception\ChannelReceptionPlanVersionResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/** 展示微信公众号渠道回收站。 */
class ListWechatOfficialAccountChannelTrashAction
{
    use AsAction;

    /** 创建回收站页面查询。 */
    public function __construct(
        private readonly ChannelReceptionPlanVersionResolver $planVersionResolver,
    ) {}

    /** 查询已删除的微信公众号渠道。 */
    public function handle(int $page = 1, int $perPage = SimplePaginationData::DEFAULT_PER_PAGE): ShowWechatOfficialAccountChannelTrashPagePropsData
    {
        $paginator = Channel::query()
            ->onlyTrashed()
            ->where('type', ChannelType::WechatOfficialAccount)
            ->with('receptionPlan')
            ->latest('deleted_at')
            ->orderByDesc('id')
            ->paginate(max(1, min($perPage, 24)), ['*'], 'page', max(1, $page));

        return new ShowWechatOfficialAccountChannelTrashPagePropsData(
            trashed_channel_list: $paginator->getCollection()->map(fn (Channel $channel) => WechatOfficialAccountData::fromModel(
                $channel,
                $this->planVersionResolver->resolveChannelStatus($channel),
            ))->all(),
            trashed_channel_list_pagination: SimplePaginationData::fromPaginator($paginator),
        );
    }

    /** 渲染微信公众号渠道回收站页面。 */
    public function asController(Request $request): Response
    {
        return Inertia::render('channel/wechat-official-account/Trash', $this->handle((int) $request->query('page', 1))->toArray());
    }
}
