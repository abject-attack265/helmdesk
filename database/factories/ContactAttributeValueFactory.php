<?php

namespace Database\Factories;

use App\Enums\AttributeValueSource;
use App\Models\AttributeDefinition;
use App\Models\Contact;
use App\Models\ContactAttributeValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactAttributeValue>
 */
class ContactAttributeValueFactory extends Factory
{
    protected $model = ContactAttributeValue::class;

    public function definition(): array
    {
        return [
            'contact_id' => Contact::factory(),
            'definition_id' => fn (): string => (string) AttributeDefinition::factory()->create()->id,
            'value_json' => ['value' => fake()->word()],
            'source' => AttributeValueSource::Manual,
        ];
    }

    public function forText(?string $value = null): static
    {
        return $this->state([
            'value_json' => ['value' => $value ?? fake()->sentence()],
        ]);
    }

    public function forNumber(int|float|null $value = null): static
    {
        return $this->state([
            'value_json' => ['value' => $value ?? fake()->randomNumber(3)],
        ]);
    }

    public function forDate(?string $value = null): static
    {
        return $this->state([
            'value_json' => ['value' => $value ?? fake()->date('Y-m-d')],
        ]);
    }

    public function forBoolean(bool $value = true): static
    {
        return $this->state([
            'value_json' => ['value' => $value],
        ]);
    }

    public function forSingleSelect(string $code): static
    {
        return $this->state([
            'value_json' => ['value' => $code],
        ]);
    }

    public function forMultiSelect(array $codes): static
    {
        return $this->state([
            'value_json' => ['value' => $codes],
        ]);
    }
}
