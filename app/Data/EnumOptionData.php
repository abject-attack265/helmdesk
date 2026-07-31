<?php

namespace App\Data;

use App\Contracts\LabeledEnum;
use BackedEnum;
use Spatie\LaravelData\Data;

/**
 * 枚举下拉选项数据。
 * 由后端枚举转换后传给前端 Select/Combobox，常见于设置页和筛选弹层；description 用于需要辅助说明的选项。
 */
class EnumOptionData extends Data
{
    public function __construct(
        public string|int $value,
        public string $label,
        public ?string $description = null,
    ) {}

    public static function fromEnum(BackedEnum&LabeledEnum $enum): self
    {
        return new self(
            value: $enum->value,
            label: $enum->label(),
            description: method_exists($enum, 'description') ? $enum->description() : null,
        );
    }

    /**
     * @param  array<int, BackedEnum&LabeledEnum>  $cases
     * @return array<int, self>
     */
    public static function fromCases(array $cases): array
    {
        return array_map(
            static fn (BackedEnum&LabeledEnum $enum) => self::fromEnum($enum),
            $cases,
        );
    }
}
