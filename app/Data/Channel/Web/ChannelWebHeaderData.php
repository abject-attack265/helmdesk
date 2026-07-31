<?php

namespace App\Data\Channel\Web;

use Spatie\LaravelData\Data;

/**
 * 网站渠道访客界面标题栏设置。
 */
class ChannelWebHeaderData extends Data
{
    public function __construct(
        public bool $enabled = false,
    ) {}
}
