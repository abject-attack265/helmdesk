<?php

namespace App\Actions\Channel\WechatOfficialAccount;

use App\Actions\Reception\Plan\ListReceptionPlansForChannelSelectionAction;
use App\Data\Channel\WechatOfficialAccount\ShowWechatOfficialAccountChannelDetailPagePropsData;
use App\Data\Channel\WechatOfficialAccount\WechatOfficialAccountData;
use App\Data\Channel\WechatOfficialAccount\WechatOfficialAccountFormOptionsData;
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

/** 展示微信公众号渠道详情。 */
class ShowWechatOfficialAccountChannelDetailPageAction
{
    use AsAction;

    /** 创建渠道详情查询。 */
    public function __construct(
        private readonly ListReceptionPlansForChannelSelectionAction $listReceptionPlans,
        private readonly ChannelReceptionPlanVersionResolver $planVersionResolver,
    ) {}

    /** 构建微信公众号渠道详情与表单选项，完整密钥仅向渠道编辑者下发。 */
    public function handle(User $actor, string $channelId): ShowWechatOfficialAccountChannelDetailPagePropsData
    {
        $channel = Channel::query()->where('type', ChannelType::WechatOfficialAccount)->with('receptionPlan')->findOrFail($channelId);

        return new ShowWechatOfficialAccountChannelDetailPagePropsData(
            wechat_channel: WechatOfficialAccountData::fromModel(
                $channel,
                $this->planVersionResolver->resolveChannelStatus($channel),
                withSecrets: Gate::forUser($actor)->allows('user.permission', UserPermission::ChannelsEdit),
            ),
            form_options: new WechatOfficialAccountFormOptionsData(
                reception_plan_options: $this->listReceptionPlans->handle(),
                reception_language_options: EnumOptionData::fromCases(ReceptionLanguage::cases()),
            ),
        );
    }

    /** 渲染微信公众号渠道详情页面。 */
    public function asController(Request $request, string $channel): Response
    {
        return Inertia::render('channel/wechat-official-account/Show', $this->handle($request->user(), $channel)->toArray());
    }
}
