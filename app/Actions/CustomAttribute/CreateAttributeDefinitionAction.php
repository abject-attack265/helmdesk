<?php

namespace App\Actions\CustomAttribute;

use App\Data\CustomAttribute\FormCreateAttributeDefinitionData;
use App\Enums\AttributeType;
use App\Models\AttributeDefinition;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * 创建联系人的自定义属性定义。
 */
class CreateAttributeDefinitionAction
{
    use AsAction;

    public function handle(FormCreateAttributeDefinitionData $data): AttributeDefinition
    {
        $this->validateKey($data->key);

        $type = AttributeType::from($data->type);
        $this->validateFilterable($type, $data->is_filterable);
        $this->validateConfig($type, $data->config);

        $maxOrder = AttributeDefinition::query()
            ->max('display_order') ?? -1;

        return AttributeDefinition::query()->create([
            'key' => $data->key,
            'name' => $data->name,
            'description' => $data->description,
            'type' => $type,
            'config' => $data->config,
            'display_order' => $maxOrder + 1,
            'is_filterable' => $data->is_filterable,
            'is_ai_readable' => $data->is_ai_readable,
            'is_ai_writable' => $data->is_ai_writable,
        ]);
    }

    public function asController(Request $request): Response
    {
        $data = FormCreateAttributeDefinitionData::from($request);

        $this->handle($data);

        return back();
    }

    private function validateKey(string $key): void
    {
        if (in_array($key, AttributeDefinition::RESERVED_KEYS, true)) {
            throw ValidationException::withMessages([
                'key' => __('custom_attribute.reserved_key', ['key' => $key]),
            ]);
        }

        $exists = AttributeDefinition::query()
            ->withTrashed()
            ->where('key', $key)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'key' => __('custom_attribute.duplicate_key', ['key' => $key]),
            ]);
        }
    }

    private function validateConfig(AttributeType $type, ?array $config): void
    {
        if (! $type->usesOptions()) {
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
    }

    private function validateFilterable(AttributeType $type, bool $isFilterable): void
    {
        if (! $isFilterable || $type->supportsFiltering()) {
            return;
        }

        throw ValidationException::withMessages([
            'is_filterable' => __('custom_attribute.unsupported_filterable_type'),
        ]);
    }
}
