<?php

namespace Database\Factories;

use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationSource;
use App\Enums\ConversationStatus;
use App\Enums\ReceptionLanguage;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ReceptionPlan;
use App\Models\ReceptionPlanVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * 构造会话测试数据及常用业务状态。
 *
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * 返回会话默认测试数据。
     */
    public function definition(): array
    {
        $createdAt = fake()->dateTimeBetween('-14 days', 'now');

        return [
            'contact_id' => null,
            'assigned_user_id' => null,
            'channel_id' => null,
            'entry_mode' => null,
            'visitor_locale' => ReceptionLanguage::ChineseSimplified->value,
            'source' => ConversationSource::Channel,
            'status' => ConversationStatus::Open,
            'inbox_status' => fake()->randomElement(ConversationInboxStatus::cases()),
            'waiting_for_visitor_reply' => false,
            'subject' => fake()->sentence(),
            'summary' => fake()->optional()->paragraph(),
            'ai_context' => ['language' => fake()->randomElement(['zh-CN', 'en-US'])],
            'last_message_preview' => fake()->sentence(),
            'last_message_at' => $createdAt,
            'unread_visitor_message_count' => 0,
            'unread_agent_message_count' => 0,
            'next_seq_no' => 0,
            'closed_at' => null,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ];
    }

    /**
     * 构造未分配负责人的会话。
     */
    public function unassigned(): static
    {
        return $this->state([
            'assigned_user_id' => null,
        ]);
    }

    /**
     * 构造不关联联系人的会话。
     */
    public function withoutContact(): static
    {
        return $this->state([
            'contact_id' => null,
        ]);
    }

    /**
     * 让会话归属指定联系人。
     */
    public function forContact(Contact $contact): static
    {
        return $this->state([
            'contact_id' => $contact->id,
        ]);
    }

    /**
     * 让会话归属指定联系人和渠道。
     */
    public function forContactChannel(Contact $contact, Channel $channel): static
    {
        return $this->state([
            'contact_id' => $contact->id,
            'channel_id' => $channel->id,
        ]);
    }

    /**
     * 让会话分配给指定用户。
     */
    public function assignedTo(User $user): static
    {
        return $this->state([
            'assigned_user_id' => $user->id,
        ]);
    }

    /**
     * 构造包含关闭时间的已关闭会话。
     */
    public function closed(): static
    {
        return $this->state(function (): array {
            $closedAt = fake()->dateTimeBetween('-7 days', 'now');

            return [
                'status' => ConversationStatus::Closed,
                'inbox_status' => ConversationInboxStatus::TeammateHandling,
                'waiting_for_visitor_reply' => false,
                'closed_at' => $closedAt,
                'last_message_at' => $closedAt,
            ];
        });
    }

    /**
     * 构造等待访客回复的会话。
     */
    public function waitingForVisitorReply(): static
    {
        return $this->state([
            'waiting_for_visitor_reply' => true,
        ]);
    }

    /**
     * 为会话创建接待方案、版本和渠道。
     */
    public function withReceptionPlanVersion(): static
    {
        $plan = ReceptionPlan::factory()->create();
        $planVersion = ReceptionPlanVersion::factory()->for($plan, 'plan')->create();
        $channel = Channel::factory()->create([
            'reception_plan_id' => $plan->id,
        ]);

        return $this->state([
            'channel_id' => $channel->id,
            'reception_plan_version_id' => $planVersion->id,
        ]);
    }
}
