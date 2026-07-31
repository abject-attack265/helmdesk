<?php

namespace App\Data\Channel\Web;

use App\Data\SimplePaginationData;
use Spatie\LaravelData\Data;

/**
 * 网站渠道回收站页面 props。
 */
class ShowWebChannelTrashPagePropsData extends Data
{
    public function __construct(
        /** @var WebChannelData[] */
        public array $trashed_channel_list,
        public SimplePaginationData $trashed_channel_list_pagination,
    ) {}
}
