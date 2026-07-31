<?php

namespace Database\Factories;

use App\Models\CannedReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CannedReply>
 */
class CannedReplyFactory extends Factory
{
    protected $model = CannedReply::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->unique()->sentence(3),
            'shortcut' => null,
            'content' => fake()->sentence(8),
            'usage_count' => 0,
            'last_used_at' => null,
            'metadata' => null,
        ];
    }

    /**
     * 标记为指定用户的个人模版。
     */
    public function ownedBy(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    /**
     * 给模版设置短码。
     */
    public function withShortcut(string $shortcut): static
    {
        return $this->state(fn () => ['shortcut' => $shortcut]);
    }
}
