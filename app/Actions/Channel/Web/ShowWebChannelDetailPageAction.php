<?php

namespace App\Actions\Channel\Web;

use App\Actions\Reception\Plan\ListReceptionPlansForChannelSelectionAction;
use App\Data\Channel\Web\ShowWebChannelDetailPagePropsData;
use App\Data\Channel\Web\WebChannelData;
use App\Data\Channel\Web\WebChannelFormOptionsData;
use App\Data\Channel\Web\WritableAttributeDefinitionOptionData;
use App\Data\EnumOptionData;
use App\Enums\AttributeType;
use App\Enums\ChannelType;
use App\Enums\ReceptionLanguage;
use App\Enums\UserPermission;
use App\Enums\WebChannelParamTarget;
use App\Enums\WebChannelParamTrust;
use App\Enums\WebChannelParamWriteMode;
use App\Enums\WebChannelQueryParam;
use App\Enums\WebChannelVisitorIdentityMode;
use App\Enums\WebChannelWidgetEntryMode;
use App\Enums\WebChannelWidgetEntryPosition;
use App\Enums\WebChannelWidgetEntryStyle;
use App\Enums\WebChannelWidgetIconSize;
use App\Models\AttributeDefinition;
use App\Models\Channel;
use App\Models\User;
use App\Services\Channel\WebChannelThemePalette;
use App\Services\Reception\ChannelReceptionPlanVersionResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 展示网站渠道详情页及各配置表单数据。
 */
class ShowWebChannelDetailPageAction
{
    use AsAction;

    /**
     * 注入接待方案选项与渠道部署状态解析器。
     */
    public function __construct(
        private ListReceptionPlansForChannelSelectionAction $listReceptionPlans,
        private ChannelReceptionPlanVersionResolver $planVersionResolver,
    ) {}

    /**
     * 组装网站渠道详情与表单选项，完整密钥仅向渠道编辑者下发。
     */
    public function handle(User $actor, string $channelId): ShowWebChannelDetailPagePropsData
    {
        $channel = Channel::query()
            ->where('type', ChannelType::Web)
            ->with(['receptionPlan'])
            ->findOrFail($channelId);

        return new ShowWebChannelDetailPagePropsData(
            web_channel: WebChannelData::fromModel(
                $channel,
                $this->planVersionResolver->resolveChannelStatus($channel),
                canRevealSecret: Gate::forUser($actor)->allows('user.permission', UserPermission::ChannelsEdit),
            ),
            form_options: new WebChannelFormOptionsData(
                reception_plan_options: $this->listReceptionPlans->handle(),
                visitor_identity_mode_options: EnumOptionData::fromCases(WebChannelVisitorIdentityMode::cases()),
                query_param_options: EnumOptionData::fromCases(WebChannelQueryParam::cases()),
                theme_color_options: WebChannelThemePalette::presets(),
                widget_entry_mode_options: EnumOptionData::fromCases(WebChannelWidgetEntryMode::cases()),
                widget_entry_position_options: EnumOptionData::fromCases(WebChannelWidgetEntryPosition::cases()),
                widget_entry_style_options: EnumOptionData::fromCases(WebChannelWidgetEntryStyle::cases()),
                widget_icon_size_options: EnumOptionData::fromCases(WebChannelWidgetIconSize::cases()),
                query_param_target_options: EnumOptionData::fromCases(WebChannelParamTarget::cases()),
                query_param_trust_options: EnumOptionData::fromCases(WebChannelParamTrust::cases()),
                query_param_write_mode_options: EnumOptionData::fromCases(WebChannelParamWriteMode::cases()),
                writable_attribute_definition_options: AttributeDefinition::query()
                    ->whereNull('deleted_at')
                    ->where('is_api_writable', true)
                    ->whereIn('type', [
                        AttributeType::Text,
                        AttributeType::Textarea,
                        AttributeType::Number,
                        AttributeType::Date,
                        AttributeType::SingleSelect,
                    ])
                    ->orderBy('display_order')
                    ->orderBy('created_at')
                    ->get()
                    ->map(fn (AttributeDefinition $definition): WritableAttributeDefinitionOptionData => WritableAttributeDefinitionOptionData::fromModel($definition))
                    ->all(),
                reception_language_options: EnumOptionData::fromCases(ReceptionLanguage::cases()),
            ),
        );
    }

    /**
     * 返回网站渠道详情页面。
     */
    public function asController(Request $request, string $channel): Response
    {
        return Inertia::render('channel/web/Show', $this->handle($request->user(), $channel)->toArray());
    }
}
