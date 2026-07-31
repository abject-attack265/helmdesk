<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 网站渠道公开页支持识别的查询参数。
 */
enum WebChannelQueryParam: string implements LabeledEnum
{
    case Locale = 'locale';
    case Name = 'name';
    case Email = 'email';
    case ExternalId = 'external_id';
    case Ref = 'ref';
    case UtmSource = 'utm_source';
    case UtmMedium = 'utm_medium';
    case UtmCampaign = 'utm_campaign';

    public function label(): string
    {
        return match ($this) {
            self::Locale => __('channel.query_param_labels.locale'),
            self::Name => __('channel.query_param_labels.name'),
            self::Email => __('channel.query_param_labels.email'),
            self::ExternalId => __('channel.query_param_labels.external_id'),
            self::Ref => __('channel.query_param_labels.ref'),
            self::UtmSource => __('channel.query_param_labels.utm_source'),
            self::UtmMedium => __('channel.query_param_labels.utm_medium'),
            self::UtmCampaign => __('channel.query_param_labels.utm_campaign'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Locale => __('channel.query_params.locale'),
            self::Name => __('channel.query_params.name'),
            self::Email => __('channel.query_params.email'),
            self::ExternalId => __('channel.query_params.external_id'),
            self::Ref => __('channel.query_params.ref'),
            self::UtmSource => __('channel.query_params.utm_source'),
            self::UtmMedium => __('channel.query_params.utm_medium'),
            self::UtmCampaign => __('channel.query_params.utm_campaign'),
        };
    }
}
