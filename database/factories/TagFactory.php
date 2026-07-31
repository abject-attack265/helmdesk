<?php

namespace Database\Factories;

use App\Enums\TagSource;
use App\Models\Tag;
use App\Models\TagGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            // 标签必属于一个组。
            'tag_group_id' => fn (): string => (string) TagGroup::factory()->create()->id,
            'name' => $name,
            'normalized_name' => mb_strtolower(trim($name)),
            'color' => fake()->optional()->hexColor(),
            'description' => fake()->optional()->sentence(),
            'source' => TagSource::Manual,
            'is_locked' => false,
        ];
    }

    /**
     * 将标签归入指定标签组。
     */
    public function forGroup(TagGroup $group): static
    {
        return $this->state(fn () => [
            'tag_group_id' => $group->id,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn () => ['is_locked' => true]);
    }
}
