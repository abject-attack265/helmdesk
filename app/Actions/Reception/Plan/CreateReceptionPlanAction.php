<?php

namespace App\Actions\Reception\Plan;

use App\Data\Reception\Config\ReceptionStrategyConfigData;
use App\Data\Reception\Form\FormCreateReceptionPlanData;
use App\Models\ReceptionPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 创建接待方案配置，并发布可供渠道选用的初始版本快照。
 */
class CreateReceptionPlanAction
{
    use AsAction;

    /**
     * 注入接待方案版本发布流程。
     */
    public function __construct(
        private readonly EnsureReceptionPlanVersionAction $ensureReceptionPlanVersion,
    ) {}

    /**
     * 创建名称唯一的接待方案并发布初始版本。
     */
    public function handle(FormCreateReceptionPlanData $data): ReceptionPlan
    {
        $name = trim($data->name);
        $this->ensureNameIsAvailable($name);

        $strategyConfig = ReceptionStrategyConfigData::fromArray($data->strategy_config)->toConfigArray();

        $plan = ReceptionPlan::query()->create([
            'name' => $name,
            'description' => filled($data->description) ? $data->description : null,
            'persona_config' => [
                'display_name' => $data->persona_display_name,
                'tone' => $data->persona_tone,
            ],
            'global_instructions' => $data->global_instructions,
            'strategy_config' => $strategyConfig,
        ]);

        $this->ensureReceptionPlanVersion->handle($plan, Auth::user());

        return $plan;
    }

    /**
     * 校验创建表单并跳转到新方案详情页。
     */
    public function asController(Request $request): RedirectResponse
    {
        $plan = $this->handle(FormCreateReceptionPlanData::from($request));

        return redirect()->route('app.manage.reception.plans.show', [
            'plan' => $plan->id,
        ]);
    }

    /**
     * 检查活跃或已删除的方案是否占用名称。
     */
    private function ensureNameIsAvailable(string $name): void
    {
        $exists = ReceptionPlan::query()
            ->withTrashed()
            ->where('name', $name)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => __('reception.messages.plan_name_exists'),
            ]);
        }
    }
}
