<?php

namespace App\Data\User;

use Spatie\LaravelData\Data;

/**
 * 双因子认证设置页面 props。
 * 由对应 Show*Action 返回给 pages/settings/*，用于渲染首屏数据和页面状态。
 */
class ShowTwoFactorAuthenticationSettingsPagePropsData extends Data
{
    public function __construct(
        public bool $twoFactorEnabled,
        public bool $requiresConfirmation,
    ) {}
}
