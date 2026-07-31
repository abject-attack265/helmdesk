<?php

namespace App\Data\Channel\Web;

use Spatie\LaravelData\Data;

/**
 * 网站渠道访客输入区设置。
 */
class ChannelWebComposerData extends Data
{
    public function __construct(
        public ?string $placeholder = null,
    ) {}

    /**
     * 用访客界面设置组装公开输入区配置。
     */
    public static function fromVisitorInterface(
        ChannelWebVisitorInterfaceSettingsData $visitorInterface,
    ): self {
        return new self(
            placeholder: filled($visitorInterface->composer_placeholder) ? $visitorInterface->composer_placeholder : null,
        );
    }
}
