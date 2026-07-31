<?php

namespace Database\Factories;

use App\Enums\TagScope;
use App\Models\TagGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TagGroup>
 */
class TagGroupFactory extends Factory
{
    protected $model = TagGroup::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name' => $name,
            'normalized_name' => mb_strtolower(trim($name)),
            'scope' => TagScope::Conversation,
            'sort_order' => 0,
        ];
    }

    public function conversation(): static
    {
        return $this->state(fn () => ['scope' => TagScope::Conversation]);
    }

    public function contact(): static
    {
        return $this->state(fn () => ['scope' => TagScope::Contact]);
    }
}
