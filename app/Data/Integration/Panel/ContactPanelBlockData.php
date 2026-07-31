<?php

namespace App\Data\Integration\Panel;

use App\Enums\ContactPanelBlockKind;
use App\Enums\ContactPanelValueType;
use Spatie\LaravelData\Data;

/**
 * 联系人面板的单个积木块（section 内的最小渲染单元）。
 *
 * 由 IntegrationPanel.vue 按 kind 分发渲染：kind=key_value 用 rows，kind=list 用 items；
 * 另一种 kind 下对应的数组保持为空。title 为块级可选小标题。
 */
class ContactPanelBlockData extends Data
{
    public function __construct(
        public ContactPanelBlockKind $kind,
        public ?string $title = null,
        /** @var list<ContactPanelKeyValueRowData> */
        public array $rows = [],
        /** @var list<ContactPanelListItemData> */
        public array $items = [],
    ) {}

    /**
     * 从业务系统返回的单个 block JSON 解析为 DTO；kind / value_type 非法的条目跳过，
     * 解析不出有效内容（空 rows / 空 items）则返回 null。
     *
     * @param  array<string, mixed>  $payload
     */
    public static function tryFromPayload(array $payload): ?self
    {
        $kind = is_string($payload['kind'] ?? null)
            ? ContactPanelBlockKind::tryFrom($payload['kind'])
            : null;
        if ($kind === null) {
            return null;
        }

        $title = is_string($payload['title'] ?? null) ? $payload['title'] : null;

        return match ($kind) {
            ContactPanelBlockKind::KeyValue => self::tryKeyValueBlock($title, $payload['rows'] ?? null),
            ContactPanelBlockKind::List => self::tryListBlock($title, $payload['items'] ?? null),
        };
    }

    /**
     * 解析 key_value 块的 rows；非法 value_type 行跳过，无有效行返回 null。
     */
    private static function tryKeyValueBlock(?string $title, mixed $rawRows): ?self
    {
        if (! is_array($rawRows)) {
            return null;
        }

        $rows = [];
        foreach ($rawRows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $valueType = is_string($row['value_type'] ?? null)
                ? ContactPanelValueType::tryFrom($row['value_type'])
                : null;
            if ($valueType === null) {
                continue;
            }
            $label = is_string($row['label'] ?? null) ? trim($row['label']) : '';
            if ($label === '') {
                continue;
            }

            $rows[] = new ContactPanelKeyValueRowData(
                label: $label,
                value: self::scalarToString($row['value'] ?? null),
                value_type: $valueType,
            );
        }

        if ($rows === []) {
            return null;
        }

        return new self(kind: ContactPanelBlockKind::KeyValue, title: $title, rows: $rows);
    }

    /**
     * 解析 list 块的 items；无标题或非数组项跳过，无有效条目返回 null。
     */
    private static function tryListBlock(?string $title, mixed $rawItems): ?self
    {
        if (! is_array($rawItems)) {
            return null;
        }

        $items = [];
        foreach ($rawItems as $item) {
            if (! is_array($item)) {
                continue;
            }
            $itemTitle = is_string($item['title'] ?? null) ? trim($item['title']) : '';
            if ($itemTitle === '') {
                continue;
            }

            $meta = [];
            if (is_array($item['meta'] ?? null)) {
                foreach ($item['meta'] as $metaValue) {
                    if (is_scalar($metaValue)) {
                        $meta[] = (string) $metaValue;
                    }
                }
            }

            $items[] = new ContactPanelListItemData(
                title: $itemTitle,
                subtitle: is_string($item['subtitle'] ?? null) ? $item['subtitle'] : null,
                meta: $meta,
                badge: is_string($item['badge'] ?? null) ? $item['badge'] : null,
                link: is_string($item['link'] ?? null) ? $item['link'] : null,
            );
        }

        if ($items === []) {
            return null;
        }

        return new self(kind: ContactPanelBlockKind::List, title: $title, items: $items);
    }

    /**
     * 把业务系统传来的标量 value 收敛成展示字符串（null 归一为空串）。
     */
    private static function scalarToString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
