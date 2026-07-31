<?php

namespace App\Actions\Channel\Web;

use App\Actions\Reception\Plan\ResolveChannelReceptionPlanAction;
use App\Data\Channel\Web\ChannelWebSettingsData;
use App\Data\Channel\Web\FormCreateWebChannelData;
use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/** 添加网站渠道并生成访问标识和访客身份密钥。 */
class CreateWebChannelAction
{
    use AsAction;

    /** 注入渠道接待方案解析器。 */
    public function __construct(
        private ResolveChannelReceptionPlanAction $resolveChannelReceptionPlan,
    ) {}

    /** 保存网站渠道及默认设置。 */
    public function handle(FormCreateWebChannelData $data): Channel
    {
        $planId = $this->resolveChannelReceptionPlan->handle(
            $data->reception_plan_id,
            requireUsable: true,
        );

        $channel = Channel::query()->create([
            'type' => ChannelType::Web,
            'name' => $data->name,
            'description' => filled($data->description) ? $data->description : null,
            'reception_plan_id' => $planId,
            'settings' => ChannelWebSettingsData::defaults([
                'allowed_embed_hosts' => ['*'],
                'default_visitor_locale' => $data->default_visitor_locale->value,
                'user_token_secret' => Str::random(64),
            ]),
        ]);

        return $channel;
    }

    /** 处理添加渠道表单并跳转到渠道详情。 */
    public function asController(Request $request): RedirectResponse
    {
        $channel = $this->handle(FormCreateWebChannelData::from($request));

        return redirect()->route('app.manage.channels.web.show', [
            'channel' => $channel->id,
        ]);
    }
}
