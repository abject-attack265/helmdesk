<?php

namespace App\Actions\Channel\WechatOfficialAccount;

use App\Actions\Reception\Plan\ResolveChannelReceptionPlanAction;
use App\Data\Channel\WechatOfficialAccount\ChannelWechatOfficialAccountSettingsData;
use App\Data\Channel\WechatOfficialAccount\FormUpdateWechatOfficialAccountChannelBasicData;
use App\Enums\ChannelType;
use App\Models\Channel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/** 更新微信公众号渠道配置。 */
class UpdateWechatOfficialAccountChannelBasicAction
{
    use AsAction;

    /** 注入渠道接待方案解析器。 */
    public function __construct(
        private readonly ResolveChannelReceptionPlanAction $resolveChannelReceptionPlan,
    ) {}

    /** 保存微信公众号基础信息与直连凭证。 */
    public function handle(Channel $channel, FormUpdateWechatOfficialAccountChannelBasicData $data): void
    {
        $planId = $this->resolveChannelReceptionPlan->handle(
            $data->reception_plan_id,
            requireUsable: $data->reception_plan_id !== $channel->reception_plan_id,
        );

        $channel->update([
            'name' => $data->name,
            'description' => filled($data->description) ? $data->description : null,
            'reception_plan_id' => $planId,
            'settings' => ChannelWechatOfficialAccountSettingsData::from([
                'app_id' => trim($data->app_id),
                'app_secret' => trim($data->app_secret),
                'token' => trim($data->token),
                'aes_key' => $data->message_mode === 'aes' ? trim((string) $data->aes_key) : '',
                'default_visitor_locale' => $data->default_visitor_locale->value,
                'visitor_message_ai_translation_enabled' => $data->visitor_message_ai_translation_enabled,
                'translation_context_hint' => filled($data->translation_context_hint) ? trim($data->translation_context_hint) : null,
            ]),
        ]);
    }

    /** 解析更新表单并返回渠道详情。 */
    public function asController(Request $request, string $channel): RedirectResponse
    {
        $channelModel = Channel::query()->where('type', ChannelType::WechatOfficialAccount)->findOrFail($channel);
        $this->handle($channelModel, FormUpdateWechatOfficialAccountChannelBasicData::from($request));

        return redirect()->route('app.manage.channels.wechat-official-account.show', ['channel' => $channelModel->id]);
    }
}
