<?php

namespace App\Data\Reception;

use Spatie\LaravelData\Data;

/**
 * AI 接待请求转人工后的确定性决策。
 */
class HandoffDecisionData extends Data
{
    public function __construct(
        public bool $accepted,
        public string $reason,
        public string $notice,
        public bool $human_available,
        public ?string $business_hours_summary = null,
        public ?string $next_available_at = null,
    ) {}
}
