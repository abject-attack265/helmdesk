<?php

namespace App\Data\Reception\Config;

use App\Enums\ReceptionPersonaTone;
use LogicException;
use Spatie\LaravelData\Data;

/**
 * 接待方案的人设配置。
 * 用于 resources/js/pages/reception/plans/* 的人设区块展示和编辑回填。
 * tone_label 跟随后端多语言下发，避免前端再维护语气值到文案的映射。
 */
class PersonaConfigData extends Data
{
    public function __construct(
        public string $display_name,
        public ReceptionPersonaTone $tone,
        public string $tone_label,
    ) {}

    /**
     * 从 ReceptionPlan.persona_config JSON 列构造展示数据。
     *
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw): self
    {
        if (! isset($raw['display_name']) || ! filled($raw['display_name'])) {
            throw new LogicException('Reception persona display_name is required.');
        }

        if (! isset($raw['tone']) || ! filled($raw['tone'])) {
            throw new LogicException('Reception persona tone is required.');
        }

        $tone = ReceptionPersonaTone::from((string) $raw['tone']);

        return new self(
            display_name: (string) $raw['display_name'],
            tone: $tone,
            tone_label: $tone->label(),
        );
    }
}
