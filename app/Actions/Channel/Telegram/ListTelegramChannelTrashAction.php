<?php

namespace App\Actions\Channel\Telegram;

use App\Data\Channel\Telegram\ShowTelegramChannelTrashPagePropsData;
use App\Data\Channel\Telegram\TelegramChannelData;
use App\Data\SimplePaginationData;
use App\Enums\ChannelType;
use App\Models\Channel;
use App\Services\Reception\ChannelReceptionPlanVersionResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 查询 Telegram 渠道回收站。
 */
class ListTelegramChannelTrashAction
{
    use AsAction;

    /**
     * 注入渠道接待方案状态解析器。
     */
    public function __construct(
        private ChannelReceptionPlanVersionResolver $planVersionResolver,
    ) {}

    /**
     * 查询当前应用已删除的 Telegram 渠道列表。
     */
    public function handle(int $page = 1, int $perPage = SimplePaginationData::DEFAULT_PER_PAGE): ShowTelegramChannelTrashPagePropsData
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 24));

        $paginator = Channel::query()
            ->onlyTrashed()
            ->where('type', ChannelType::Telegram)
            ->with(['receptionPlan'])
            ->latest('deleted_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);

        return new ShowTelegramChannelTrashPagePropsData(
            trashed_channel_list: $paginator->getCollection()
                ->map(fn (Channel $channel) => TelegramChannelData::fromModel(
                    $channel,
                    $this->planVersionResolver->resolveChannelStatus($channel),
                ))
                ->all(),
            trashed_channel_list_pagination: SimplePaginationData::fromPaginator($paginator),
        );
    }

    /**
     * 返回 Telegram 渠道回收站页面。
     */
    public function asController(Request $request): Response
    {

        return Inertia::render('channel/telegram/Trash', $this->handle(
            page: (int) $request->query('page', 1),
        )->toArray());
    }
}
