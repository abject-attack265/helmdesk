<?php

namespace App\Data\AiCallLog;

use App\Data\EnumOptionData;
use App\Data\SimplePaginationData;
use Spatie\LaravelData\Data;

/**
 * 应用设置「AI 调用日志」列表页 props。
 * 由 ShowAiCallLogListAction 返回，传给 resources/js/pages/appSettings/aiCallLog/List.vue。
 */
class ShowAiCallLogListPagePropsData extends Data
{
    public function __construct(
        /** @var ListAiCallLogItemData[] */
        public array $logs,
        public SimplePaginationData $pagination,
        public AiCallLogFilterData $filters,
        /** @var EnumOptionData[] */
        public array $purpose_options,
        /** @var EnumOptionData[] */
        public array $status_options,
    ) {}
}
