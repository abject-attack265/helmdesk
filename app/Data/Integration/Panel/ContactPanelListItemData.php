<?php

namespace App\Data\Integration\Panel;

use Spatie\LaravelData\Data;

/**
 * 联系人面板 list 积木块的单个条目（如一条订单 / 一个工单）。
 *
 * 由 IntegrationPanel.vue 的 ListBlock 渲染：title 主标题、subtitle 副标题、
 * meta 次要信息（金额 / 时间等，前端间隔排列）、badge 中性状态徽标、link 可选外链。
 */
class ContactPanelListItemData extends Data
{
    public function __construct(
        public string $title,
        public ?string $subtitle = null,
        /** @var list<string> */
        public array $meta = [],
        public ?string $badge = null,
        public ?string $link = null,
    ) {}
}
