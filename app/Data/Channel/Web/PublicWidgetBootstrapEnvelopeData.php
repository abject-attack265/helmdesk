<?php

namespace App\Data\Channel\Web;

use Spatie\LaravelData\Data;

/**
 * widget bootstrap 接口的响应封装：渠道外观 + CORS 决策。
 *
 * 业务侧 PublicStandaloneChannelData 保持纯净（只描述渠道外观），
 * cors_allow_origin 根据 allowed_embed_hosts 决策：
 *  - "*"     白名单未配置，允许任意 Origin（CSRF 安全由 widget bootstrap 接口本身的只读语义保证）
 *  - "match" 白名单配置且 embedHost 已命中，应回写访客实际的 Origin 头
 *
 * 渠道完全拒绝某来源时，业务 Action 会直接抛 AccessDeniedHttpException，不会构造本 envelope。
 */
class PublicWidgetBootstrapEnvelopeData extends Data
{
    public function __construct(
        public PublicStandaloneChannelData $channel,
        public string $cors_allow_origin,
    ) {}
}
