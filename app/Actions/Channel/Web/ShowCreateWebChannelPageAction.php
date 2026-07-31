<?php

namespace App\Actions\Channel\Web;

use App\Actions\Reception\Plan\ListReceptionPlansForChannelSelectionAction;
use App\Data\Channel\Web\ShowCreateWebChannelPagePropsData;
use App\Data\EnumOptionData;
use App\Enums\ReceptionLanguage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 打开创建网站渠道页面，并提供可绑定的接待方案选项。
 */
class ShowCreateWebChannelPageAction
{
    use AsAction;

    /**
     * 注入可绑定接待方案查询动作。
     */
    public function __construct(
        private ListReceptionPlansForChannelSelectionAction $listReceptionPlans,
    ) {}

    /**
     * 组装创建网站渠道页面需要的表单选项。
     */
    public function handle(): ShowCreateWebChannelPagePropsData
    {
        return new ShowCreateWebChannelPagePropsData(
            reception_plan_options: $this->listReceptionPlans->handle(),
            reception_language_options: EnumOptionData::fromCases(ReceptionLanguage::cases()),
        );
    }

    /**
     * 返回创建网站渠道页面。
     */
    public function asController(Request $request): Response
    {

        return Inertia::render('channel/web/Create', $this->handle()->toArray());
    }
}
