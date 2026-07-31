<?php

namespace App\Actions\Reception;

use App\Enums\ConversationEntryMode;
use App\Enums\ConversationEventType;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationSource;
use App\Enums\ConversationStatus;
use App\Enums\ReceptionRoutingMode;
use App\Models\Channel;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ConversationEvent;
use App\Models\ConversationThread;
use App\Services\Reception\ChannelActivePlanVersionResolver;
use App\Services\Reception\ChannelAiAvailability;
use App\Services\Reception\ChannelTeammateAvailability;
use App\Services\Reception\ReceptionPlanStrategyResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;

/**
 * 按渠道、联系人和接待策略查找或创建访客会话。
 */
class FindOrCreateReceptionConversationAction
{
    use AsAction;

    /**
     * 注入接待策略、服务可用性、方案版本和超时接管服务。
     */
    public function __construct(
        private readonly ChannelAiAvailability $aiAvailability,
        private readonly ChannelTeammateAvailability $teammateAvailability,
        private readonly ReceptionPlanStrategyResolver $strategyResolver,
        private readonly ChannelActivePlanVersionResolver $activePlanVersionResolver,
        private readonly TakeOverReceptionConversationByAiAction $takeOverByAi,
    ) {}

    /**
     * 返回当前开放会话，或创建会话并初始化接待状态。
     *
     * @return array{0: Conversation, 1: bool} [conversation, created]
     */
    public function handle(
        Channel $channel,
        Contact $contact,
        ConversationEntryMode $entryMode,
        string $defaultVisitorLocale,
    ): array {
        $existing = $this->openConversationQuery($channel, $contact)->first();
        if ($existing !== null) {
            // 访客活动触发即时超时接管；定时扫描处理没有后续请求的会话。
            $this->takeOverByAi->handle($channel, $existing);

            return [$existing, false];
        }

        if ($channel->trashed()) {
            throw new GoneHttpException('channel is paused');
        }

        [$conversation, $created] = DB::transaction(function () use ($channel, $contact, $entryMode, $defaultVisitorLocale): array {
            $lockedContact = Contact::query()
                ->whereKey($contact->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedContact instanceof Contact) {
                Log::warning('[reception] 联系人在创建会话前不可用', [
                    'channel_id' => (string) $channel->id,
                    'contact_id' => (string) $contact->id,
                ]);

                throw new GoneHttpException('contact is unavailable');
            }

            $existing = $this->openConversationQuery($channel, $lockedContact)->first();
            if ($existing !== null) {
                return [$existing, false];
            }

            $conversation = Conversation::query()->create([
                'contact_id' => $lockedContact->id,
                'channel_id' => $channel->id,
                'reception_plan_version_id' => $this->activePlanVersionResolver
                    ->currentVersionForChannel($channel)?->id,
                'visitor_locale' => $defaultVisitorLocale,
                'entry_mode' => $entryMode,
                'source' => ConversationSource::Channel,
                'status' => ConversationStatus::Open,
                'inbox_status' => $this->resolveInitialInboxStatus($channel, $lockedContact),
            ]);

            ConversationEvent::query()->create([
                'conversation_id' => $conversation->id,
                'type' => ConversationEventType::Created,
                'payload' => ['source' => 'reception'],
                'created_at' => now(),
            ]);

            return [$conversation, true];
        });

        if (! $created) {
            $this->takeOverByAi->handle($channel, $conversation);

            return [$conversation, false];
        }

        $thread = ConversationThread::requireForConversation($conversation);

        $logContext = [
            'channel_id' => (string) $channel->id,
            'contact_id' => (string) $contact->id,
            'thread_id' => (string) $thread->id,
            'conversation_id' => (string) $conversation->id,
            'entry_mode' => $entryMode->value,
            'inbox_status' => $conversation->inbox_status->value,
            'reception_plan_version_id' => filled($conversation->reception_plan_version_id)
                ? (string) $conversation->reception_plan_version_id
                : null,
        ];
        DB::afterCommit(
            static fn () => Log::info('[reception] 新建接待会话', $logContext),
        );

        $conversation->refresh();

        return [$conversation, true];
    }

    /**
     * 根据路由模式、重点客户策略和服务可用性决定初始接待状态。
     */
    private function resolveInitialInboxStatus(Channel $channel, Contact $contact): ConversationInboxStatus
    {
        $strategy = $this->strategyResolver->forChannel($channel);

        if ($strategy->reception_mode === ReceptionRoutingMode::TeammateFirst) {
            return ConversationInboxStatus::TeammatePending;
        }

        if (
            $contact->is_important
            && $strategy->important_contact_human_first_when_online_enabled
            && $this->teammateAvailability->serviceStatus($channel)->human_available
        ) {
            return ConversationInboxStatus::TeammatePending;
        }

        return $this->aiAvailability->canUseAi($channel)
            ? ConversationInboxStatus::AiHandling
            : ConversationInboxStatus::TeammatePending;
    }

    /**
     * 构造访客在当前渠道下的开放会话查询。
     */
    private function openConversationQuery(Channel $channel, Contact $contact): Builder
    {
        return Conversation::query()
            ->where('channel_id', $channel->id)
            ->where('contact_id', $contact->id)
            ->where('status', ConversationStatus::Open);
    }
}
