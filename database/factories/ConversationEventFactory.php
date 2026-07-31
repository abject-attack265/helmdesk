<?php

namespace Database\Factories;

use App\Enums\ConversationEventType;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationEvent>
 */
class ConversationEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => fn (): string => (string) Conversation::factory()->create()->id,
            'actor_user_id' => null,
            'type' => ConversationEventType::Created,
            'payload' => ['source' => 'manual'],
            'created_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ];
    }

    public function forConversation(Conversation $conversation): static
    {
        return $this->state([
            'conversation_id' => $conversation->id,
        ]);
    }

    public function handoffRequested(): static
    {
        return $this->state([
            'actor_user_id' => null,
            'type' => ConversationEventType::HandoffRequested,
            'payload' => ['reason' => 'tool_failure'],
        ]);
    }
}
