<?php

namespace Database\Factories;

use App\Enums\AttributeType;
use App\Models\AttributeDefinition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttributeDefinition>
 */
class AttributeDefinitionFactory extends Factory
{
    protected $model = AttributeDefinition::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->regexify('[a-z]{4,8}_[a-z]{3,6}'),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'type' => AttributeType::Text,
            'config' => null,
            'display_order' => 0,
            'is_filterable' => false,
        ];
    }

    public function text(): static
    {
        return $this->state([
            'type' => AttributeType::Text,
            'config' => null,
        ]);
    }

    public function textarea(): static
    {
        return $this->state([
            'type' => AttributeType::Textarea,
            'config' => null,
        ]);
    }

    public function number(): static
    {
        return $this->state([
            'type' => AttributeType::Number,
            'config' => null,
        ]);
    }

    public function date(): static
    {
        return $this->state([
            'type' => AttributeType::Date,
            'config' => null,
        ]);
    }

    public function boolean(): static
    {
        return $this->state([
            'type' => AttributeType::Boolean,
            'config' => null,
        ]);
    }

    public function singleSelect(array $options = []): static
    {
        if (empty($options)) {
            $options = [
                ['code' => 'option_a', 'label' => 'Option A'],
                ['code' => 'option_b', 'label' => 'Option B'],
                ['code' => 'option_c', 'label' => 'Option C'],
            ];
        }

        return $this->state([
            'type' => AttributeType::SingleSelect,
            'config' => ['options' => $options],
        ]);
    }

    public function multiSelect(array $options = []): static
    {
        if (empty($options)) {
            $options = [
                ['code' => 'tag_a', 'label' => 'Tag A'],
                ['code' => 'tag_b', 'label' => 'Tag B'],
                ['code' => 'tag_c', 'label' => 'Tag C'],
            ];
        }

        return $this->state([
            'type' => AttributeType::MultiSelect,
            'config' => ['options' => $options],
        ]);
    }

    public function archived(): static
    {
        return $this->state([
            'deleted_at' => now(),
        ]);
    }

    public function deleted(): static
    {
        return $this->archived();
    }
}
