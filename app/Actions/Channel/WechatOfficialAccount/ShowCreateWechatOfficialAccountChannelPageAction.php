<?php

namespace App\Actions\Channel\WechatOfficialAccount;

use App\Actions\Reception\Plan\ListReceptionPlansForChannelSelectionAction;
use App\Data\Channel\WechatOfficialAccount\ShowCreateWechatOfficialAccountChannelPagePropsData;
use App\Data\EnumOptionData;
use App\Enums\ReceptionLanguage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/** 展示微信公众号渠道创建页面。 */
class ShowCreateWechatOfficialAccountChannelPageAction
{
    use AsAction;

    /** 创建渠道表单选项查询。 */
    public function __construct(
        private readonly ListReceptionPlansForChannelSelectionAction $listReceptionPlans,
    ) {}

    /** 构建微信公众号渠道创建表单选项。 */
    public function handle(): ShowCreateWechatOfficialAccountChannelPagePropsData
    {
        return new ShowCreateWechatOfficialAccountChannelPagePropsData(
            reception_plan_options: $this->listReceptionPlans->handle(),
            reception_language_options: EnumOptionData::fromCases(ReceptionLanguage::cases()),
        );
    }

    /** 渲染微信公众号渠道创建页面。 */
    public function asController(Request $request): Response
    {
        return Inertia::render('channel/wechat-official-account/Create', $this->handle()->toArray());
    }
}
