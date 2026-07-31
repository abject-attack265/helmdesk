<?php

namespace App\Actions\Channel\Telegram;

use App\Actions\Reception\Plan\ListReceptionPlansForChannelSelectionAction;
use App\Data\Channel\Telegram\ShowCreateTelegramChannelPagePropsData;
use App\Data\EnumOptionData;
use App\Enums\ReceptionLanguage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 打开创建 Telegram 渠道页面，并提供可部署的接待方案选项。
 */
class ShowCreateTelegramChannelPageAction
{
    use AsAction;

    /**
     * 注入可部署接待方案查询动作。
     */
    public function __construct(
        private ListReceptionPlansForChannelSelectionAction $listReceptionPlans,
    ) {}

    /**
     * 组装创建 Telegram 渠道页面需要的表单选项。
     */
    public function handle(): ShowCreateTelegramChannelPagePropsData
    {
        return new ShowCreateTelegramChannelPagePropsData(
            reception_plan_options: $this->listReceptionPlans->handle(),
            reception_language_options: EnumOptionData::fromCases(ReceptionLanguage::cases()),
        );
    }

    /**
     * 返回创建 Telegram 渠道页面。
     */
    public function asController(Request $request): Response
    {

        return Inertia::render('channel/telegram/Create', $this->handle()->toArray());
    }
}
