<?php

namespace App\Services\CustomAttribute;

use App\Enums\AttributeType;
use App\Models\AttributeDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * 处理自定义属性筛选条件。
 */
class ScopedAttributeFilterHelper
{
    /**
     * 按属性定义清洗筛选参数。
     *
     * @param  Collection<string, AttributeDefinition>  $definitionsByKey
     * @param  array<string, mixed>  $attributeFilters
     */
    public function normalizeFilters(Collection $definitionsByKey, array $attributeFilters): array
    {
        $normalizedFilters = [];

        foreach (array_keys($attributeFilters) as $key) {
            if (! is_string($key) || ! $definitionsByKey->has($key)) {
                throw ValidationException::withMessages([
                    'attribute_filters' => __('custom_attribute.invalid_attribute_filter'),
                ]);
            }
        }

        foreach ($definitionsByKey as $key => $definition) {
            $rawValue = $attributeFilters[$key] ?? null;

            if ($rawValue === null || $rawValue === '') {
                continue;
            }

            $normalizedValue = match ($definition->type) {
                AttributeType::SingleSelect => $this->normalizeSingleSelectFilter($definition, $rawValue),
                AttributeType::Boolean => $this->normalizeBooleanFilter($rawValue),
                AttributeType::Number => $this->normalizeNumberFilter($rawValue),
                AttributeType::Date => $this->normalizeDateFilter($rawValue),
                default => throw ValidationException::withMessages([
                    "attribute_filters.{$key}" => __('custom_attribute.unsupported_filterable_type'),
                ]),
            };

            if ($normalizedValue === null) {
                throw ValidationException::withMessages([
                    "attribute_filters.{$key}" => __('custom_attribute.invalid_attribute_filter'),
                ]);
            }

            $normalizedFilters[$key] = $normalizedValue;
        }

        return $normalizedFilters;
    }

    /**
     * 把属性筛选条件应用到查询上。
     *
     * @param  Builder<Model>|HasMany<Model>  $query
     * @param  Collection<string, AttributeDefinition>  $definitionsByKey
     * @param  array<string, mixed>  $attributeFilters
     */
    public function applyFilters(
        Builder|HasMany $query,
        Collection $definitionsByKey,
        array $attributeFilters,
        string $valueTable,
        string $ownerKeyColumn,
    ): void {
        if (empty($attributeFilters)) {
            return;
        }

        $ownerTable = $query->getModel()->getTable();

        foreach ($attributeFilters as $key => $filterValue) {
            $definition = $definitionsByKey->get($key);

            if (! $definition) {
                continue;
            }

            $query->whereExists(function ($subQuery) use ($definition, $filterValue, $ownerTable, $valueTable, $ownerKeyColumn) {
                $subQuery->selectRaw('1')
                    ->from($valueTable.' as attribute_filter_values')
                    ->whereColumn('attribute_filter_values.'.$ownerKeyColumn, "{$ownerTable}.id")
                    ->where('attribute_filter_values.definition_id', $definition->id);

                match ($definition->type) {
                    AttributeType::SingleSelect, AttributeType::Boolean => $subQuery
                        ->where('attribute_filter_values.value_json->value', $filterValue),
                    AttributeType::Number => $subQuery
                        ->when(array_key_exists('min', $filterValue), fn ($numberQuery) => $numberQuery->where('attribute_filter_values.value_json->value', '>=', $filterValue['min']))
                        ->when(array_key_exists('max', $filterValue), fn ($numberQuery) => $numberQuery->where('attribute_filter_values.value_json->value', '<=', $filterValue['max'])),
                    AttributeType::Date => $subQuery
                        ->when(array_key_exists('from', $filterValue), fn ($dateQuery) => $dateQuery->where('attribute_filter_values.value_json->value', '>=', $filterValue['from']))
                        ->when(array_key_exists('to', $filterValue), fn ($dateQuery) => $dateQuery->where('attribute_filter_values.value_json->value', '<=', $filterValue['to'])),
                    default => null,
                };
            });
        }
    }

    /**
     * 校验并返回单选筛选值。
     */
    private function normalizeSingleSelectFilter(AttributeDefinition $definition, mixed $rawValue): ?string
    {
        if (! is_string($rawValue) || $rawValue === '') {
            return null;
        }

        $validCodes = collect($definition->config['options'] ?? [])
            ->pluck('code');

        return $validCodes->contains($rawValue) ? $rawValue : null;
    }

    /**
     * 把布尔筛选值转成布尔类型。
     */
    private function normalizeBooleanFilter(mixed $rawValue): ?bool
    {
        return match ($rawValue) {
            true, 1, '1', 'true' => true,
            false, 0, '0', 'false' => false,
            default => null,
        };
    }

    /**
     * 整理数值范围筛选。
     *
     * @return array{min?: int|float, max?: int|float}|null
     */
    private function normalizeNumberFilter(mixed $rawValue): ?array
    {
        if (! is_array($rawValue)) {
            return null;
        }

        $result = [];

        if (array_key_exists('min', $rawValue) && $rawValue['min'] !== '' && is_numeric($rawValue['min'])) {
            $result['min'] = $rawValue['min'] + 0;
        }

        if (array_key_exists('max', $rawValue) && $rawValue['max'] !== '' && is_numeric($rawValue['max'])) {
            $result['max'] = $rawValue['max'] + 0;
        }

        return $result === [] ? null : $result;
    }

    /**
     * 整理日期范围筛选。
     *
     * @return array{from?: string, to?: string}|null
     */
    private function normalizeDateFilter(mixed $rawValue): ?array
    {
        if (! is_array($rawValue)) {
            return null;
        }

        $result = [];

        if (array_key_exists('from', $rawValue) && $this->isValidDateFilterBoundary($rawValue['from'])) {
            $result['from'] = $rawValue['from'];
        }

        if (array_key_exists('to', $rawValue) && $this->isValidDateFilterBoundary($rawValue['to'])) {
            $result['to'] = $rawValue['to'];
        }

        return $result === [] ? null : $result;
    }

    /**
     * 检查日期边界是否是 YYYY-MM-DD。
     */
    private function isValidDateFilterBoundary(mixed $value): bool
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }
}
