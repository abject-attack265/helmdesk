<?php

namespace App\Data\Integration;

use Spatie\LaravelData\Data;

/**
 * 集成列表页使用的集成和工具数据。
 */
class ShowInstanceIntegrationsPagePropsData extends Data
{
    public function __construct(
        /** @var IntegrationData[] */
        public array $servers,
    ) {}
}
