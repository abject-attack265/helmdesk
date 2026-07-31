<?php

namespace App\Data\Integration;

use App\Data\EnumOptionData;
use Spatie\LaravelData\Data;

/**
 * 集成创建页使用的类型选项。
 */
class ShowCreateIntegrationPagePropsData extends Data
{
    public function __construct(
        /** @var EnumOptionData[] */
        public array $provider_options,
    ) {}
}
