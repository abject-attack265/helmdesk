<?php

namespace App\Data\Channel\Telegram;

use Spatie\LaravelData\Data;

/**
 * Telegram 渠道详情页面 props。
 * 由 ShowTelegramChannelDetailPageAction 返回给 resources/js/pages/channel/telegram/Show.vue。
 */
class ShowTelegramChannelDetailPagePropsData extends Data
{
    public function __construct(
        public TelegramChannelData $telegram_channel,
        public TelegramChannelFormOptionsData $form_options,
    ) {}
}
