<?php

namespace App\Data\Channel\Web;

use App\Enums\WebChannelVisitorIdentityMode;
use App\Services\Channel\WebChannelThemePalette;
use Illuminate\Validation\Rule;
use Spatie\LaravelData\Data;

/**
 * 更新网站渠道访客界面表单数据。
 * 来自 resources/js/pages/channel/web/tabs/VisitorInterfaceTab.vue 的访客界面表单提交。
 */
class FormUpdateWebChannelVisitorInterfaceData extends Data
{
    public function __construct(
        public ?string $site_name = null,
        public ?string $subtitle = null,
        public bool $header_enabled = false,
        public ?string $icon_id = null,
        public WebChannelVisitorIdentityMode $visitor_identity_mode = WebChannelVisitorIdentityMode::ActualReceptionist,
        public ?string $service_display_name = null,
        public ?string $service_avatar_id = null,
        public ?string $greeting_message = null,
        public ?string $composer_placeholder = null,
        public string $theme_color = WebChannelThemePalette::DEFAULT,
        public bool $home_mode_enabled = false,
        public ?string $home_welcome_message = null,
        public bool $suggestions_enabled = false,
        /** @var string[]|null */
        public ?array $suggestion_items = null,
    ) {}

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'site_name' => [Rule::requiredIf(request()->boolean('header_enabled')), 'nullable', 'string', 'max:100'],
            'subtitle' => ['nullable', 'string', 'max:120'],
            'header_enabled' => ['required', 'boolean'],
            'icon_id' => ['nullable', 'string'],
            'visitor_identity_mode' => ['required', 'string', Rule::in(array_column(WebChannelVisitorIdentityMode::cases(), 'value'))],
            'service_display_name' => ['nullable', 'string', 'max:100'],
            'service_avatar_id' => ['nullable', 'string'],
            'greeting_message' => ['nullable', 'string', 'max:1000'],
            'composer_placeholder' => ['nullable', 'string', 'max:120'],
            'theme_color' => ['required', 'string', 'regex:'.WebChannelThemePalette::HEX_PATTERN],
            'home_mode_enabled' => ['required', 'boolean'],
            'home_welcome_message' => [Rule::requiredIf(request()->boolean('home_mode_enabled')), 'nullable', 'string', 'max:50'],
            'suggestions_enabled' => ['boolean'],
            'suggestion_items' => ['nullable', 'array', 'max:'.ChannelWebSuggestionsData::MaxItems],
            'suggestion_items.*' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * 清理并去重猜你想问列表。
     *
     * @return string[]
     */
    public function normalizedSuggestionItems(): array
    {
        return collect($this->suggestion_items ?? [])
            ->map(fn (mixed $value) => is_string($value) ? trim($value) : '')
            ->filter(fn (string $value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }
}
