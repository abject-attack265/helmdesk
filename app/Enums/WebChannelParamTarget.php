<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 网站渠道自定义查询参数的写入目标。
 *
 * Attribute / Tag 需要 target_key 指定具体自定义属性 key 或标签名称模板。
 */
enum WebChannelParamTarget: string implements LabeledEnum
{
    case ContactName = 'contact_name';
    case ContactEmail = 'contact_email';
    case ContactPhone = 'contact_phone';
    case ContactExternalId = 'contact_external_id';
    case ContactImportance = 'contact_importance';
    case Attribute = 'attribute';
    case Tag = 'tag';

    public function label(): string
    {
        return match ($this) {
            self::ContactName => __('channel.web.param_targets.contact_name'),
            self::ContactEmail => __('channel.web.param_targets.contact_email'),
            self::ContactPhone => __('channel.web.param_targets.contact_phone'),
            self::ContactExternalId => __('channel.web.param_targets.contact_external_id'),
            self::ContactImportance => __('channel.web.param_targets.contact_importance'),
            self::Attribute => __('channel.web.param_targets.attribute'),
            self::Tag => __('channel.web.param_targets.tag'),
        };
    }

    /**
     * 是否需要 target_key 字段（自定义属性 key 或标签名称模板）。
     */
    public function requiresTargetKey(): bool
    {
        return match ($this) {
            self::Attribute, self::Tag => true,
            default => false,
        };
    }
}
