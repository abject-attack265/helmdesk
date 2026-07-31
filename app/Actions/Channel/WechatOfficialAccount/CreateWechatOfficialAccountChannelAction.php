<?php

namespace App\Actions\Channel\WechatOfficialAccount;

use App\Actions\Reception\Plan\ResolveChannelReceptionPlanAction;
use App\Data\Channel\WechatOfficialAccount\ChannelWechatOfficialAccountSettingsData;
use App\Data\Channel\WechatOfficialAccount\FormCreateWechatOfficialAccountChannelData;
use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/** 添加待配置的微信公众号渠道。 */
class CreateWechatOfficialAccountChannelAction
{
    use AsAction;

    /** 注入渠道接待方案解析器。 */
    public function __construct(
        private readonly ResolveChannelReceptionPlanAction $resolveChannelReceptionPlan,
    ) {}

    /** 保存微信公众号渠道及默认设置。 */
    public function handle(FormCreateWechatOfficialAccountChannelData $data): Channel
    {
        $planId = $this->resolveChannelReceptionPlan->handle($data->reception_plan_id, requireUsable: true);

        $channel = Channel::query()->create([
            'type' => ChannelType::WechatOfficialAccount,
            'name' => $data->name,
            'description' => filled($data->description) ? $data->description : null,
            'reception_plan_id' => $planId,
            'settings' => new ChannelWechatOfficialAccountSettingsData(
                default_visitor_locale: $data->default_visitor_locale,
            ),
        ]);

        return $channel;
    }

    /** 解析创建表单并跳转到渠道详情。 */
    public function asController(Request $request): RedirectResponse
    {
        $channel = $this->handle(FormCreateWechatOfficialAccountChannelData::from($request));

        return redirect()->route('app.manage.channels.wechat-official-account.show', [
            'channel' => $channel->id,
            'tab' => 'wechat',
        ]);
    }
}
