<?php

namespace App\Actions\Channel\Telegram;

use App\Actions\Reception\Plan\ResolveChannelReceptionPlanAction;
use App\Data\Channel\Telegram\ChannelTelegramSettingsData;
use App\Data\Channel\Telegram\FormCreateTelegramChannelData;
use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/** 添加待连接的 Telegram 机器人渠道。 */
class CreateTelegramChannelAction
{
    use AsAction;

    /**
     * 注入渠道接待方案解析器。
     */
    public function __construct(
        private readonly ResolveChannelReceptionPlanAction $resolveChannelReceptionPlan,
    ) {}

    /**
     * 保存渠道基本信息并生成消息校验密钥。
     */
    public function handle(FormCreateTelegramChannelData $data): Channel
    {
        $planId = $this->resolveChannelReceptionPlan->handle(
            $data->reception_plan_id,
            requireUsable: true,
        );

        $channel = Channel::query()->create([
            'type' => ChannelType::Telegram,
            'name' => $data->name,
            'description' => filled($data->description) ? $data->description : null,
            'reception_plan_id' => $planId,
            'settings' => ChannelTelegramSettingsData::defaults([
                'webhook_secret' => Str::random(48),
                'default_visitor_locale' => $data->default_visitor_locale->value,
            ]),
        ]);

        return $channel;
    }

    /**
     * 处理添加渠道表单并跳转到机器人接入页。
     */
    public function asController(Request $request): RedirectResponse
    {

        $channel = $this->handle(FormCreateTelegramChannelData::from($request));

        return redirect()->route('app.manage.channels.telegram.show', [
            'channel' => $channel->id,
            'tab' => 'telegram',
        ]);
    }
}
