<?php

namespace App\Data\Channel\Web;

use App\Enums\ReceptionLanguage;
use Spatie\LaravelData\Data;

/** 网站渠道设置数据。 */
class ChannelWebSettingsData extends Data
{
    /**
     * 创建网站渠道设置；嵌入域为 null 或空数组时不限制来源。
     *
     * @param  list<string>|null  $allowed_embed_hosts
     * @param  list<WebChannelQueryParamMappingData>  $query_param_mappings
     */
    public function __construct(
        public ChannelWebWidgetSettingsData $widget = new ChannelWebWidgetSettingsData,
        public ChannelWebVisitorInterfaceSettingsData $visitor_interface = new ChannelWebVisitorInterfaceSettingsData(
            header: new ChannelWebHeaderData,
        ),
        public ChannelWebSuggestionsData $suggestions = new ChannelWebSuggestionsData,
        public ReceptionLanguage $default_visitor_locale = ReceptionLanguage::ChineseSimplified,
        public ?array $allowed_embed_hosts = null,
        public ?string $user_token_secret = null,
        public array $query_param_mappings = [],
        public ?string $standalone_link_query = null,
        public bool $visitor_message_ai_translation_enabled = false,
        public ?string $translation_context_hint = null,
    ) {}

    /**
     * 使用默认值与指定覆盖项构造网站渠道设置。
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function defaults(array $overrides = []): self
    {
        return self::from(self::mergeSettings([
            'widget' => ChannelWebWidgetSettingsData::defaults()->toArray(),
            'visitor_interface' => ChannelWebVisitorInterfaceSettingsData::defaults()->toArray(),
            'suggestions' => ChannelWebSuggestionsData::defaults()->toArray(),
            'default_visitor_locale' => ReceptionLanguage::ChineseSimplified->value,
            'allowed_embed_hosts' => null,
            'user_token_secret' => null,
            'query_param_mappings' => [],
            'standalone_link_query' => null,
            'visitor_message_ai_translation_enabled' => false,
            'translation_context_hint' => null,
        ], $overrides));
    }

    /**
     * 基于当前设置合并局部覆盖值。
     *
     * @param  array<string, mixed>  $overrides
     */
    public function mergeWith(array $overrides): self
    {
        return self::defaults(self::mergeSettings($this->toArray(), $overrides));
    }

    /**
     * 递归合并设置；普通对象配置递归合并，列表字段整体替换。
     *
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function mergeSettings(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (
                is_array($value)
                && isset($base[$key])
                && is_array($base[$key])
                && ! array_is_list($base[$key])
                && ! array_is_list($value)
            ) {
                $base[$key] = self::mergeSettings($base[$key], $value);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}
