<?php

namespace App\Data\Channel\Telegram;

use App\Data\SimplePaginationData;
use Spatie\LaravelData\Data;

/**
 * Telegram 渠道列表页面 props。
 * 由 ListTelegramChannelsAction 返回给 resources/js/pages/channel/telegram/List.vue 渲染列表。
 */
class ShowTelegramChannelListPagePropsData extends Data
{
    public function __construct(
        /** @var TelegramChannelData[] */
        public array $channel_list,
        public SimplePaginationData $channel_list_pagination,
    ) {}
}
