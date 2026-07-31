<?php

namespace App\Data\Reception;

use App\Enums\HumanServiceUnavailableReason;
use Spatie\LaravelData\Data;

/**
 * 渠道人工服务的当前运行时状态，供接待路由、AI prompt 和转人工工具共用。
 */
class HumanServiceStatusData extends Data
{
    public function __construct(
        public string $timezone,
        public string $now_local,
        public bool $business_hours_enabled,
        public bool $within_business_hours,
        public bool $has_online_teammate,
        public bool $human_available,
        public ?HumanServiceUnavailableReason $unavailable_reason,
        public string $business_hours_summary,
        public ?string $next_available_at = null,
    ) {}
}
