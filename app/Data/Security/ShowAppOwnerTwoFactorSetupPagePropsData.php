<?php

namespace App\Data\Security;

use Spatie\LaravelData\Data;

/**
 * 应用所有者两步验证初始化页状态，待确认时包含扫码密钥。
 */
class ShowAppOwnerTwoFactorSetupPagePropsData extends Data
{
    /**
     * 保存密钥生成、确认与扫码配置状态；确认完成后密钥字段为 null。
     */
    public function __construct(
        public bool $two_factor_enabled,
        public bool $two_factor_confirmed,
        public ?string $qr_code_svg,
        public ?string $manual_setup_key,
    ) {}
}
