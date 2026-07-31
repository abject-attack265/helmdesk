<?php

namespace App\Data\Integration\Panel;

use Spatie\LaravelData\Data;

/**
 * 单个集成对某联系人产出的业务数据面板（统一展示描述符）。
 *
 * 由 BuildContactIntegrationPanelsAction 收集后下发给接待页右侧「资料」tab 底部的
 * IntegrationPanel.vue 渲染；provider 把自家数据映射成固定积木，前端不感知具体平台。
 * integration_id / integration_name 标识来源集成，provider_label 为 provider 展示文案。
 */
class ContactPanelData extends Data
{
    public function __construct(
        public string $integration_id,
        public string $integration_name,
        public string $provider_label,
        /** @var list<ContactPanelSectionData> */
        public array $sections = [],
    ) {}

    /**
     * 把业务系统返回的描述符 JSON（{ sections: [...] }）连同来源集成元信息解析为面板 DTO；
     * 非法 / 空块的 section 跳过，无任何有效 section 返回 null（表示该集成对此联系人无数据）。
     *
     * @param  array<string, mixed>  $payload
     */
    public static function tryFromBusinessSystemPayload(
        array $payload,
        string $integrationId,
        string $integrationName,
        string $providerLabel,
    ): ?self {
        $rawSections = $payload['sections'] ?? null;
        if (! is_array($rawSections)) {
            return null;
        }

        $sections = [];
        foreach ($rawSections as $rawSection) {
            if (! is_array($rawSection)) {
                continue;
            }
            $section = ContactPanelSectionData::tryFromPayload($rawSection);
            if ($section !== null) {
                $sections[] = $section;
            }
        }

        if ($sections === []) {
            return null;
        }

        return new self(
            integration_id: $integrationId,
            integration_name: $integrationName,
            provider_label: $providerLabel,
            sections: $sections,
        );
    }
}
