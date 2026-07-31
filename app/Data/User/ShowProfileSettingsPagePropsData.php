<?php

namespace App\Data\User;

use Spatie\LaravelData\Data;

/**
 * 配置设置页面 props。
 * 由对应 Show*Action 返回给 pages/settings/*，用于渲染首屏数据和页面状态。
 */
class ShowProfileSettingsPagePropsData extends Data
{
    public function __construct(
        public bool $mustVerifyEmail,
        public ?string $status,
    ) {}
}
