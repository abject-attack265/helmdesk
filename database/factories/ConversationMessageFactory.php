<?php

namespace Database\Factories;

use App\Enums\MessageKind;
use App\Enums\MessageRole;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationMessage>
 */
class ConversationMessageFactory extends Factory
{
    public function definition(): array
    {
        $createdAt = fake()->dateTimeBetween('-7 days', 'now');

        return [
            'conversation_id' => fn (): string => (string) Conversation::factory()->create()->id,
            'sender_user_id' => null,
            'role' => MessageRole::Teammate,
            'sender_name' => function (array $attributes): string {
                $role = $attributes['role'] instanceof MessageRole
                    ? $attributes['role']
                    : MessageRole::from((string) $attributes['role']);

                return match ($role) {
                    MessageRole::Visitor => $this->visitorSenderName($attributes),
                    MessageRole::Ai => 'AI',
                    MessageRole::Teammate => $this->teammateSenderName($attributes),
                    MessageRole::Tool => 'Tool',
                };
            },
            'kind' => MessageKind::Text,
            'content' => fake()->sentence(),
            'payload' => null,
            'confidence' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    public function forConversation(Conversation $conversation): static
    {
        return $this->state([
            'conversation_id' => $conversation->id,
        ]);
    }

    public function visitorText(): static
    {
        return $this->state([
            'sender_user_id' => null,
            'role' => MessageRole::Visitor,
            'kind' => MessageKind::Text,
            'content' => fake()->sentence(),
        ]);
    }

    public function aiText(): static
    {
        return $this->state([
            'sender_user_id' => null,
            'role' => MessageRole::Ai,
            'kind' => MessageKind::Text,
            'content' => fake()->sentence(),
        ]);
    }

    public function aiSummary(): static
    {
        return $this->state([
            'sender_user_id' => null,
            'role' => MessageRole::Ai,
            'kind' => MessageKind::Summary,
            'content' => fake()->paragraph(),
        ]);
    }

    public function toolCall(): static
    {
        return $this->state([
            'sender_user_id' => null,
            'role' => MessageRole::Ai,
            'kind' => MessageKind::ToolCall,
            'content' => null,
            'payload' => [
                'tool' => 'search_docs',
                'arguments' => ['query' => fake()->sentence()],
            ],
        ]);
    }

    public function toolResult(): static
    {
        return $this->state([
            'sender_user_id' => null,
            'role' => MessageRole::Tool,
            'kind' => MessageKind::ToolResult,
            'content' => null,
            'payload' => [
                'status' => 'ok',
                'result' => fake()->sentence(),
            ],
        ]);
    }

    public function recalled(): static
    {
        return $this->state([
            'recalled_at' => now(),
        ]);
    }

    public function withClientMsgId(string $clientMsgId): static
    {
        return $this->state([
            'client_msg_id' => $clientMsgId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function visitorSenderName(array $attributes): string
    {
        $conversationId = $attributes['conversation_id'] ?? null;
        $conversation = is_string($conversationId)
            ? Conversation::query()->with('contact')->find($conversationId)
            : null;

        return (string) $conversation?->contact?->name;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function teammateSenderName(array $attributes): string
    {
        $senderUserId = $attributes['sender_user_id'] ?? null;
        $sender = is_string($senderUserId) ? User::query()->find($senderUserId) : null;

        return $sender?->name ?? 'Test Agent';
    }
}
