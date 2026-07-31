<?php

namespace App\Data\Contact;

use Spatie\LaravelData\Data;

/**
 * 联系人接手简报的结构化生成结果。
 */
class GeneratedContactHandoffBriefData extends Data
{
    /**
     * 创建结构化接手简报。
     *
     * @param  list<string>  $next_actions
     */
    public function __construct(
        public string $brief,
        public array $next_actions,
    ) {}
}
