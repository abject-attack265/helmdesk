<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 网站渠道查询参数信任级别。
 *
 * - SignedOnly：仅在签名访客身份（user_token 校验通过）的请求中才采纳此参数，
 *   避免任何人在 URL 里随手把 admin@example.com 当邮箱写入联系人。
 * - Always：任意访客都可写入；适合 utm_source / referrer 这种营销分析字段。
 */
enum WebChannelParamTrust: string implements LabeledEnum
{
    case SignedOnly = 'signed_only';
    case Always = 'always';

    public function label(): string
    {
        return match ($this) {
            self::SignedOnly => __('channel.web.param_trust.signed_only'),
            self::Always => __('channel.web.param_trust.always'),
        };
    }
}
