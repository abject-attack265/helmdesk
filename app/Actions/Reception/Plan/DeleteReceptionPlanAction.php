<?php

namespace App\Actions\Reception\Plan;

use App\Enums\ConversationStatus;
use App\Exceptions\BusinessException;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\ReceptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 删除未被渠道和进行中会话使用的接待方案。
 */
class DeleteReceptionPlanAction
{
    use AsAction;

    /**
     * 渠道或进行中会话仍使用方案时阻止删除，否则将方案移入回收站。
     */
    public function handle(ReceptionPlan $plan): void
    {
        $channelReferenceCount = Channel::query()
            ->where('reception_plan_id', $plan->id)
            ->count();

        if ($channelReferenceCount > 0) {
            throw new BusinessException(__('reception.messages.plan_in_use_channel', [
                'count' => $channelReferenceCount,
            ]));
        }

        $versionIds = $plan->versions()->pluck('id');

        if ($versionIds->isNotEmpty()) {
            $conversationReferenceCount = Conversation::query()
                ->whereIn('reception_plan_version_id', $versionIds)
                ->where('status', ConversationStatus::Open)
                ->count();

            if ($conversationReferenceCount > 0) {
                throw new BusinessException(__('reception.messages.plan_in_use_conversation', [
                    'count' => $conversationReferenceCount,
                ]));
            }
        }

        $plan->delete();
    }

    /**
     * 删除方案并返回列表。
     */
    public function asController(Request $request, string $plan): RedirectResponse
    {
        $planModel = ReceptionPlan::query()
            ->findOrFail($plan);

        $this->handle($planModel);

        return redirect()->route('app.manage.reception.plans.index');
    }
}
