<?php

namespace App\Actions\CustomAttribute;

use App\Data\CustomAttribute\FormUpdateAttributeDefinitionData;
use App\Enums\AttributeType;
use App\Models\AttributeDefinition;
use App\Models\ContactAttributeValue;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * 更新自定义属性名称、说明、选项和筛选能力。
 */
class UpdateAttributeDefinitionAction
{
    use AsAction;

    public function handle(string $definitionId, FormUpdateAttributeDefinitionData $data): AttributeDefinition
    {
        $definition = AttributeDefinition::query()->findOrFail($definitionId);

        $this->validateFilterable($definition, $data->is_filterable);
        $this->validateConfig($definition, $data->config);

        $definition->update([
            'name' => $data->name,
            'description' => $data->description,
            'config' => $data->config,
            'is_filterable' => $data->is_filterable,
            'is_ai_readable' => $data->is_ai_readable,
            'is_ai_writable' => $data->is_ai_writable,
        ]);

        return $definition;
    }

    public function asController(Request $request, string $id): Response
    {
        $data = FormUpdateAttributeDefinitionData::from($request);

        $this->handle($id, $data);

        return back();
    }

    private function validateConfig(AttributeDefinition $definition, ?array $config): void
    {
        if (! $definition->usesOptions()) {
            return;
        }

        $options = $config['options'] ?? [];

        if (empty($options)) {
            throw ValidationException::withMessages([
                'config' => __('custom_attribute.invalid_option_config'),
            ]);
        }

        $codes = [];
        foreach ($options as $option) {
            if (empty($option['code']) || ! isset($option['label']) || $option['label'] === '') {
                throw ValidationException::withMessages([
                    'config' => __('custom_attribute.invalid_option_config'),
                ]);
            }

            if (in_array($option['code'], $codes, true)) {
                throw ValidationException::withMessages([
                    'config' => __('custom_attribute.option_code_duplicate'),
                ]);
            }

            $codes[] = $option['code'];
        }

        $oldCodes = collect($definition->config['options'] ?? [])->pluck('code')->all();
        $newCodes = collect($options)->pluck('code')->all();
        $removedCodes = array_diff($oldCodes, $newCodes);

        if (! empty($removedCodes)) {
            $usedCodes = $this->findUsedOptionCodes($definition, $removedCodes);

            if (! empty($usedCodes)) {
                throw ValidationException::withMessages([
                    'config' => __('custom_attribute.option_code_in_use', ['code' => implode(', ', $usedCodes)]),
                ]);
            }
        }
    }

    /**
     * @param  string[]  $codes
     * @return string[]
     */
    private function findUsedOptionCodes(AttributeDefinition $definition, array $codes): array
    {
        $values = ContactAttributeValue::query()
            ->where('definition_id', $definition->id)
            ->pluck('value_json');

        $usedCodes = [];

        foreach ($values as $valueJson) {
            $val = data_get($valueJson, 'value');

            if ($definition->type === AttributeType::MultiSelect && is_array($val)) {
                foreach ($val as $code) {
                    if (in_array($code, $codes, true)) {
                        $usedCodes[] = $code;
                    }
                }
            } elseif (in_array($val, $codes, true)) {
                $usedCodes[] = $val;
            }
        }

        return array_unique($usedCodes);
    }

    private function validateFilterable(AttributeDefinition $definition, bool $isFilterable): void
    {
        if (! $isFilterable || $definition->type->supportsFiltering()) {
            return;
        }

        throw ValidationException::withMessages([
            'is_filterable' => __('custom_attribute.unsupported_filterable_type'),
        ]);
    }
}
