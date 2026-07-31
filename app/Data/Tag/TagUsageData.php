<?php

namespace App\Data\Tag;

use Spatie\LaravelData\Data;

/**
 * 标签使用量数据。
 */
class TagUsageData extends Data
{
    public function __construct(
        public int $contact_usage_count,
        public int $usage_count,
    ) {}
}
