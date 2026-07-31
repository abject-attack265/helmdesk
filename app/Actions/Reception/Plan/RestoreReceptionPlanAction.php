<?php

namespace App\Actions\Reception\Plan;

use App\Models\ReceptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 恢复已删除的接待方案。
 */
class RestoreReceptionPlanAction
{
    use AsAction;

    /**
     * 恢复指定接待方案。
     */
    public function handle(ReceptionPlan $plan): void
    {
        $plan->restore();
    }

    /**
     * 接收恢复请求并返回上一页。
     */
    public function asController(Request $request, string $plan): RedirectResponse
    {

        $planModel = ReceptionPlan::query()
            ->onlyTrashed()

            ->findOrFail($plan);

        $this->handle($planModel);

        return back();
    }
}
