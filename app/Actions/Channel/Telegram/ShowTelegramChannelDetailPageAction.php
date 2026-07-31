<?php

namespace App\Actions\Channel\Telegram;

use App\Actions\Reception\Plan\ListReceptionPlansForChannelSelectionAction;
use App\Data\Channel\Telegram\ShowTelegramChannelDetailPagePropsData;
use App\Data\Channel\Telegram\TelegramChannelData;
use App\Data\Channel\Telegram\TelegramChannelFormOptionsData;
use App\Data\EnumOptionData;
use App\Enums\ChannelType;
use App\Enums\ReceptionLanguage;
use App\Enums\UserPermission;
use App\Models\Channel;
use App\Models\User;
use App\Services\Reception\ChannelReceptionPlanVersionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 展示 Telegram 渠道详情页及基本信息表单数据。
 */
class ShowTelegramChannelDetailPageAction
{
    use AsAction;

    /**
     * 注入接待方案选项与渠道部署版本状态解析器。
     */
    public function __construct(
        private ListReceptionPlansForChannelSelectionAction $listReceptionPlans,
        private ChannelReceptionPlanVersionResolver $planVersionResolver,
    ) {}

    /**
     * 组装 Telegram 渠道详情与表单选项，完整密钥仅向渠道编辑者下发。
     */
    public function handle(User $actor, string $channelId): ShowTelegramChannelDetailPagePropsData
    {
        $channel = Channel::query()
            ->where('type', ChannelType::Telegram)
            ->with(['receptionPlan'])
            ->findOrFail($channelId);

        return new ShowTelegramChannelDetailPagePropsData(
            telegram_channel: TelegramChannelData::fromModel(
                $channel,
                $this->planVersionResolver->resolveChannelStatus($channel),
                withSecrets: Gate::forUser($actor)->allows('user.permission', UserPermission::ChannelsEdit),
            ),
            form_options: new TelegramChannelFormOptionsData(
                reception_plan_options: $this->listReceptionPlans->handle(),
                reception_language_options: EnumOptionData::fromCases(ReceptionLanguage::cases()),
            ),
        );
    }

    /**
     * 返回 Telegram 渠道详情页面。
     */
    public function asController(Request $request, string $channel): Response
    {
        return Inertia::render('channel/telegram/Show', $this->handle($request->user(), $channel)->toArray());
    }
}
