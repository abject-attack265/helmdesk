<?php

namespace Database\Factories;

use App\Enums\ContactSource;
use App\Enums\ContactType;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(ContactType::cases()),
            'source' => fake()->randomElement(ContactSource::cases()),
            'name' => fake()->name(),
            'avatar_url' => Contact::DEFAULT_AVATAR_URL,
            'locale' => fake()->optional()->randomElement(['zh_CN', 'en']),
            'timezone' => fake()->optional()->timezone(),
            'country' => fake()->optional()->country(),
            'city' => fake()->optional()->city(),
        ];
    }

    public function visitor(): static
    {
        return $this->state(['type' => ContactType::Visitor]);
    }

    public function contact(): static
    {
        return $this->state(['type' => ContactType::Contact]);
    }

    public function anonymous(): static
    {
        return $this->state([
            'type' => ContactType::Visitor,
            'name' => null,
        ]);
    }

    public function withAiContext(): static
    {
        return $this->state([
            'ai_context' => [
                'preferences' => fake()->sentence(),
                'past_issues' => fake()->sentence(),
                'sentiment' => fake()->randomElement(['positive', 'neutral', 'negative']),
                '_updated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function fromSource(ContactSource $source): static
    {
        return $this->state(['source' => $source]);
    }
}
