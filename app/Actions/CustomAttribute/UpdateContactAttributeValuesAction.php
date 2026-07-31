<?php

namespace App\Actions\CustomAttribute;

use App\Data\CurrentUserContextData;
use App\Data\CustomAttribute\FormUpdateContactAttributeValuesData;
use App\Enums\AttributeValueSource;
use App\Models\AttributeDefinition;
use App\Models\Contact;
use App\Models\ContactActivityLog;
use App\Models\ContactAttributeValue;
use App\Services\CustomAttribute\AttributeValueNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * 批量更新联系人上的自定义属性值。
 */
class UpdateContactAttributeValuesAction
{
    use AsAction;

    public function handle(string $contactId, array $attributes, int|string|null $userId = null): void
    {
        $contact = Contact::query()

            ->findOrFail($contactId);

        $definitions = AttributeDefinition::query()
            ->withTrashed()
            ->get()
            ->keyBy('key');

        $existingValues = ContactAttributeValue::query()

            ->where('contact_id', $contact->id)
            ->get()
            ->keyBy('definition_id');

        $changed = [];

        DB::transaction(function () use ($contact, $attributes, $definitions, $existingValues, $userId, &$changed) {
            foreach ($attributes as $key => $rawValue) {
                $definition = $definitions->get($key);

                if (! $definition) {
                    continue;
                }

                if ($definition->trashed()) {
                    throw ValidationException::withMessages([
                        "attributes.{$key}" => __('custom_attribute.attribute_archived'),
                    ]);
                }

                $normalizedValue = AttributeValueNormalizer::normalize($definition, $rawValue);
                $isEmpty = AttributeValueNormalizer::isEmpty($definition, $normalizedValue);
                $existing = $existingValues->get($definition->id);
                $oldValue = $existing?->value();

                if ($isEmpty) {
                    if ($existing) {
                        $changed[] = ['key' => $key, 'old' => $oldValue, 'new' => null];
                        $existing->delete();
                    }
                } else {
                    $this->validateValue($definition, $normalizedValue);
                    $valueJson = ['value' => $normalizedValue];

                    if ($existing) {
                        if ($oldValue !== $normalizedValue) {
                            $changed[] = ['key' => $key, 'old' => $oldValue, 'new' => $normalizedValue];
                        }
                        $existing->update([
                            'value_json' => $valueJson,
                            'source' => AttributeValueSource::Manual,
                            'updated_by_user_id' => $userId,
                        ]);
                    } else {
                        $changed[] = ['key' => $key, 'old' => null, 'new' => $normalizedValue];
                        ContactAttributeValue::query()->create([
                            'contact_id' => $contact->id,
                            'definition_id' => $definition->id,
                            'value_json' => $valueJson,
                            'source' => AttributeValueSource::Manual,
                            'updated_by_user_id' => $userId,
                        ]);
                    }
                }
            }

            if (! empty($changed)) {
                ContactActivityLog::query()->create([
                    'contact_id' => $contact->id,
                    'actor_user_id' => $userId,
                    'action' => 'custom_attributes_updated',
                    'created_at' => now(),
                    'payload' => ['changed' => $changed],
                ]);
            }
        });
    }

    public function asController(Request $request, string $id): Response
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $data = FormUpdateContactAttributeValuesData::from($request);

        $this->handle($id, $data->attributes, $request->user()?->id);

        return back();
    }

    /**
     * 校验值是否符合属性类型约束，不符合抛字段级验证异常。
     */
    private function validateValue(AttributeDefinition $definition, mixed $value): void
    {
        if (! AttributeValueNormalizer::isValid($definition, $value)) {
            throw ValidationException::withMessages([
                "attributes.{$definition->key}" => __('custom_attribute.invalid_attribute_value', ['name' => $definition->name]),
            ]);
        }
    }
}
