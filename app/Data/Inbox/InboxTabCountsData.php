<?php

namespace App\Data\Inbox;

use Spatie\LaravelData\Data;

/**
 * 收件箱 Tab 待关注数量。
 */
class InboxTabCountsData extends Data
{
    public function __construct(
        public int $pending,
        public int $ai,
        public int $mine,
        public int $teammates,
    ) {}
}
