<?php

namespace App\Data\Integration;

use Spatie\LaravelData\Data;

/**
 * 集成编辑页使用的表单初始数据。
 *
 * 集成类型和传输协议固定，编辑页不下发类型选项。
 */
class ShowEditIntegrationPagePropsData extends Data
{
    public function __construct(
        public IntegrationData $server,
    ) {}
}
