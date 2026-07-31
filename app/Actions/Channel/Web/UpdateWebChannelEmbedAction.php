<?php

namespace App\Actions\Channel\Web;

use App\Data\Channel\Web\FormUpdateWebChannelEmbedData;
use App\Data\Channel\Web\WebChannelQueryParamMappingData;
use App\Enums\WebChannelParamTarget;
use App\Models\Channel;
use App\Services\Channel\WebChannelResolutionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 更新网站渠道明文业务参数自动写入规则。
 */
class UpdateWebChannelEmbedAction
{
    use AsAction;

    /**
     * 注入渠道解析服务。
     */
    public function __construct(
        private readonly WebChannelResolutionService $resolution,
    ) {}

    /** 仅更新渠道的业务参数映射。 */
    public function handle(Channel $channel, FormUpdateWebChannelEmbedData $data): void
    {
        $mappings = $this->normalizeMappings($data->query_param_mappings?->toCollection()->all() ?? []);

        $currentSettings = $channel->webSettings();

        $settings = $currentSettings->mergeWith([
            'query_param_mappings' => array_map(
                static fn (WebChannelQueryParamMappingData $mapping): array => $mapping->toArray(),
                $mappings,
            ),
        ]);

        $channel->update([
            'settings' => $settings,
        ]);
    }

    /**
     * 接收业务参数映射表单并返回渠道详情页。
     */
    public function asController(Request $request, string $channel): RedirectResponse
    {

        $channelModel = $this->resolution->find($channel);

        $this->handle($channelModel, FormUpdateWebChannelEmbedData::from($request));

        return redirect()->back(302, [], route('app.manage.channels.web.show', [
            'channel' => $channelModel->id,
        ]));
    }

    /**
     * 规范化映射列表；同一 param_name、target 和 target_key 组合保留最后一条。
     * 属性 / 标签类必须填写 target_key。
     *
     * @param  list<WebChannelQueryParamMappingData>  $mappings
     * @return list<WebChannelQueryParamMappingData>
     */
    private function normalizeMappings(array $mappings): array
    {
        $byKey = [];

        foreach ($mappings as $index => $mapping) {
            $targetKey = $mapping->target_key !== null ? trim($mapping->target_key) : null;

            if ($mapping->target->requiresTargetKey() && ($targetKey ?? '') === '') {
                throw ValidationException::withMessages([
                    "query_param_mappings.{$index}.target_key" => __('validation.required', ['attribute' => 'target_key']),
                ]);
            }

            if ($mapping->target === WebChannelParamTarget::Tag) {
                if (mb_strlen((string) $targetKey) > 120) {
                    throw ValidationException::withMessages([
                        "query_param_mappings.{$index}.target_key" => __('validation.max.string', ['attribute' => 'target_key', 'max' => 120]),
                    ]);
                }
            }

            $key = $mapping->param_name.'|'.$mapping->target->value.'|'.($targetKey ?? '');
            $byKey[$key] = new WebChannelQueryParamMappingData(
                param_name: $mapping->param_name,
                target: $mapping->target,
                target_key: $targetKey,
                trust: $mapping->trust,
                write_mode: $mapping->write_mode,
            );
        }

        return array_values($byKey);
    }
}
