<?php

namespace App\Actions\Reception\Plan;

use App\Data\EnumOptionData;
use App\Data\Reception\PageProps\CreateReceptionPlanPagePropsData;
use App\Enums\ReceptionPersonaTone;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 渲染创建接待方案页（Create.vue）。
 * 仅提供基础信息表单所需的语气风格选项；其余配置在创建后于详情页完善。
 */
class ShowCreateReceptionPlanPageAction
{
    use AsAction;

    /**
     * 组装创建页表单选项。
     */
    public function handle(): CreateReceptionPlanPagePropsData
    {
        return new CreateReceptionPlanPagePropsData(
            persona_tone_options: EnumOptionData::fromCases(ReceptionPersonaTone::cases()),
        );
    }

    /**
     * Controller 入口：鉴权后渲染创建页。
     */
    public function asController(Request $request): Response
    {

        return Inertia::render('reception/plans/Create', $this->handle()->toArray());
    }
}
