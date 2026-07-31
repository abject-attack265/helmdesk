<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 网站渠道查询参数写入模式：
 *
 * - OnlyIfEmpty：仅在目标字段为空时写入；默认值，避免覆盖客服或访客手动修改的资料。
 * - Overwrite：每次都用新值覆盖；适合明确希望以 URL 为准的字段（例如 utm_source）。
 */
enum WebChannelParamWriteMode: string implements LabeledEnum
{
    case OnlyIfEmpty = 'only_if_empty';
    case Overwrite = 'overwrite';

    public function label(): string
    {
        return match ($this) {
            self::OnlyIfEmpty => __('channel.web.param_write_modes.only_if_empty'),
            self::Overwrite => __('channel.web.param_write_modes.overwrite'),
        };
    }
}
