<?php

namespace App\Data\Channel\Web;

use App\Enums\WebChannelParamTarget;
use App\Enums\WebChannelParamTrust;
use App\Enums\WebChannelParamWriteMode;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/**
 * 更新网站渠道明文业务参数自动写入规则的表单数据。
 *
 * - query_param_mappings: 列表为权威值，提交即覆盖；具体语义见 ApplyVisitorQueryParamsAction。
 * - 嵌入域名白名单不在此表单，由接入方式表单（FormUpdateWebChannelAccessData）单独维护。
 */
class FormUpdateWebChannelEmbedData extends Data
{
    public function __construct(
        /** @var DataCollection<int, WebChannelQueryParamMappingData>|null */
        #[DataCollectionOf(WebChannelQueryParamMappingData::class)]
        public ?DataCollection $query_param_mappings = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'query_param_mappings' => ['nullable', 'array', 'max:32'],
            'query_param_mappings.*.param_name' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9_.\-]+$/'],
            'query_param_mappings.*.target' => ['required', Rule::enum(WebChannelParamTarget::class)],
            'query_param_mappings.*.target_key' => ['nullable', 'string', 'max:120'],
            'query_param_mappings.*.trust' => ['required', Rule::enum(WebChannelParamTrust::class)],
            'query_param_mappings.*.write_mode' => ['required', Rule::enum(WebChannelParamWriteMode::class)],
        ];
    }
}
