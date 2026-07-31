<?php

namespace App\Data\Channel\Web;

use Spatie\LaravelData\Data;

/**
 * 网站渠道猜你想问设置。
 */
class ChannelWebSuggestionsData extends Data
{
    public const int MaxItems = 6;

    public function __construct(
        public bool $enabled = false,
        /** @var string[] */
        public array $items = [],
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     */
    public static function defaults(array $overrides = []): self
    {
        return self::from(array_replace_recursive([
            'enabled' => false,
            'items' => [],
        ], $overrides));
    }
}
