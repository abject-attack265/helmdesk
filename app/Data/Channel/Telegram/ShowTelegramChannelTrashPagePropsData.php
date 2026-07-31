<?php

namespace App\Data\Channel\Telegram;

use App\Data\SimplePaginationData;
use Spatie\LaravelData\Data;

/**
 * Telegram 渠道回收站页面 props。
 * 由 ListTelegramChannelTrashAction 返回给 resources/js/pages/channel/telegram/Trash.vue。
 */
class ShowTelegramChannelTrashPagePropsData extends Data
{
    public function __construct(
        /** @var TelegramChannelData[] */
        public array $trashed_channel_list,
        public SimplePaginationData $trashed_channel_list_pagination,
    ) {}
}
