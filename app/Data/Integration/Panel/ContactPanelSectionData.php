<?php

namespace App\Data\Integration\Panel;

use Spatie\LaravelData\Data;

/**
 * 联系人面板的一个分区（如「客户概况」「最近订单」），由若干积木块组成。
 *
 * 由 IntegrationPanel.vue 用 Collapsible 渲染：collapsed_by_default 控制初始折叠态，
 * title 为分区标题；blocks 为分区内的积木块列表。
 */
class ContactPanelSectionData extends Data
{
    public function __construct(
        public ?string $title,
        public bool $collapsed_by_default = false,
        /** @var list<ContactPanelBlockData> */
        public array $blocks = [],
    ) {}

    /**
     * 从业务系统返回的单个 section JSON 解析为 DTO；非法 / 空块跳过，无有效块返回 null。
     *
     * @param  array<string, mixed>  $payload
     */
    public static function tryFromPayload(array $payload): ?self
    {
        $rawBlocks = $payload['blocks'] ?? null;
        if (! is_array($rawBlocks)) {
            return null;
        }

        $blocks = [];
        foreach ($rawBlocks as $rawBlock) {
            if (! is_array($rawBlock)) {
                continue;
            }
            $block = ContactPanelBlockData::tryFromPayload($rawBlock);
            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        if ($blocks === []) {
            return null;
        }

        return new self(
            title: is_string($payload['title'] ?? null) ? $payload['title'] : null,
            collapsed_by_default: (bool) ($payload['collapsed'] ?? false),
            blocks: $blocks,
        );
    }
}
