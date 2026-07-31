<?php

namespace App\Jobs\Reception;

use App\Actions\Reception\AppendAiMessageAction;
use App\Actions\Reception\HandleAiUnavailableAction;
use App\Actions\Reception\LoadReceptionRuntimeAction;
use App\Data\AiRuntime\RuntimeModelCandidateData;
use App\Data\Reception\Runtime\ReceptionToolEventContextData;
use App\Enums\AiCallPurpose;
use App\Enums\AiModelPurpose;
use App\Enums\ConversationInboxStatus;
use App\Exceptions\AllModelsExhaustedException;
use App\Models\Attachment;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Services\Ai\MultimodalMessageBuilder;
use App\Services\Ai\Usage\AiUsageContext;
use App\Services\AiRuntime\AiModelFallback;
use App\Services\AiRuntime\MediaAwareModelCandidatePrioritizer;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use App\Services\Reception\ReceptionGroundingProbe;
use App\Services\Reception\ReceptionHistory;
use App\Services\Reception\ReceptionPreemptionSignal;
use App\Services\Reception\ReceptionToolFactory;
use App\Services\Reception\ReceptionToolsetBuilder;
use App\Services\Reception\ReceptionTurnDelivery;
use App\Services\Reception\ReceptionTurnExecutor;
use App\Services\Reception\ReceptionTurnOutcome;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use NeuronAI\Chat\Messages\ContentBlocks\TextContent;
use NeuronAI\Chat\Messages\UserMessage;

/**
 * 执行一次接待 turn。
 *
 * 每轮从数据库加载状态并依次尝试候选模型，同一会话通过 WithoutOverlapping 串行执行。
 * 新访客消息会抢占当前轮次，被抢占的产物不投递，由下一轮使用完整历史重新回复。
 */
class RunReceptionTurnJob implements ShouldQueue
{
    use Queueable;

    /**
     * 整轮任务允许两个 60 秒模型尝试，并为历史加载、工具调用和消息投递预留时间。
     */
    public int $timeout = 180;

    /**
     * 真实执行抛出的异常上限：一次失败即终止，不对故障模型反复重试。
     * 被 WithoutOverlapping 挡住而 release 回队列不计入此处（不是异常），由 retryUntil 控制其重试窗口。
     */
    public int $maxExceptions = 1;

    /**
     * 创建待执行的接待轮次。
     *
     * @param  list<string>  $messageIds  本轮聚合的访客文本消息 DB ID（用于历史去重与引用目标）
     * @param  list<string>  $mediaMessageIds  本轮携带的图片/视频消息 DB ID，按候选模型能力构建新消息并从历史去重
     */
    public function __construct(
        public readonly string $conversationId,
        public readonly string $aggregatedText,
        public readonly array $messageIds,
        public readonly array $mediaMessageIds,
        public readonly string $activityId,
    ) {
        $this->queue = 'interactive-ai';
    }

    /**
     * 同会话单飞：拿不到锁的 job 释放回队列稍后接管。
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->conversationId))
                ->releaseAfter(5)
                // 锁的存活时间覆盖任务硬超时，保证同一会话串行执行。
                ->expireAfter(210),
        ];
    }

    /**
     * 允许被单飞锁释放回队列的任务在锁到期后继续执行。
     * 五分钟窗口覆盖 210 秒锁周期和队列调度延迟。
     */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addMinutes(5);
    }

    /**
     * 编排一次接待 turn。
     */
    public function handle(
        LoadReceptionRuntimeAction $loadRuntime,
        ReceptionHistory $history,
        ReceptionToolFactory $tools,
        ReceptionToolsetBuilder $toolsetBuilder,
        AiModelFallback $fallback,
        ReceptionPreemptionSignal $preemption,
        ReceptionTurnExecutor $executor,
        AppendAiMessageAction $append,
        HandleAiUnavailableAction $aiUnavailable,
        MultimodalMessageBuilder $messageBuilder,
        MediaAwareModelCandidatePrioritizer $candidatePrioritizer,
        ReceptionRealtimeNotifier $realtimeNotifier,
    ): void {
        // 成功投递标记防止队列重投产生重复回复。
        if (Cache::get($this->completionKey()) === true) {
            Log::info('[reception] 跳过已完成的 AI 接待轮次', [
                'conversation_id' => $this->conversationId,
            ]);
            $realtimeNotifier->aiTurnStopped($this->conversationId, $this->activityId);

            return;
        }

        $turnId = (string) Str::uuid();
        $preemption->beginTurn($this->conversationId, $turnId);
        $realtimeNotifier->aiTurnStarted($this->conversationId, $this->activityId);
        $releaseActivity = true;

        try {
            $runtime = $loadRuntime->handle($this->conversationId);
            if (! $runtime->available) {
                Log::info('[reception] 跳过不可用的 AI 接待轮次', [
                    'conversation_id' => $this->conversationId,
                    'reason' => $runtime->reason->value,
                ]);

                return;
            }

            $conversation = Conversation::query()->findOrFail($this->conversationId);

            Log::info('[reception] AI 接待轮次开始', [
                'conversation_id' => $this->conversationId,
                'turn_id' => $turnId,
                'reception_plan_version_id' => $runtime->reception_plan_version_id,
                'text_message_count' => count($this->messageIds),
                'media_message_count' => count($this->mediaMessageIds),
            ]);

            // 当前轮文本与媒体消息作为最新 user 消息，不重复进入历史。
            $skipIds = array_merge($this->messageIds, $this->mediaMessageIds);
            $conversationHistory = $history->currentMessages($conversation, $skipIds);
            $contactHistoryContext = $history->contactContext($conversation);
            $mediaAttachments = $this->loadMediaAttachments();
            $requiresImageInput = $mediaAttachments->contains(
                static fn (Attachment $attachment): bool => Str::startsWith((string) $attachment->mime_type, 'image/'),
            );
            $requiresVideoInput = $mediaAttachments->contains(
                static fn (Attachment $attachment): bool => Str::startsWith((string) $attachment->mime_type, 'video/'),
            );
            $modelCandidates = $candidatePrioritizer->prioritize(
                $runtime->model_candidates,
                $requiresImageInput,
                $requiresVideoInput,
            );
            $this->logCandidatePriorityChange(
                $runtime->model_candidates,
                $modelCandidates,
                $requiresImageInput,
                $requiresVideoInput,
                $turnId,
            );
            $quotedId = $runtime->quote_visitor_message_enabled ? array_last($this->messageIds) : null;
            $delivery = new ReceptionTurnDelivery;
            $groundingProbe = new ReceptionGroundingProbe;
            $toolset = $toolsetBuilder->build(
                $tools->buildRespondTool($conversation, $this->conversationId, $turnId, $delivery, $runtime->reception_plan_version_id),
                $tools->buildHandoffTool($conversation, $quotedId),
                $runtime,
                $groundingProbe,
            );

            try {
                /** @var ReceptionTurnOutcome $outcome */
                $outcome = $fallback->run(
                    $modelCandidates,
                    fn (RuntimeModelCandidateData $candidate): ReceptionTurnOutcome => $executor->execute(
                        $candidate,
                        $runtime->system_prompt,
                        $toolset->tools,
                        $conversationHistory,
                        $contactHistoryContext,
                        $this->buildNewMessage($messageBuilder, $candidate, $mediaAttachments),
                        $this->conversationId,
                        $turnId,
                        AiUsageContext::forCandidate(
                            $candidate,
                            AiModelPurpose::ReceptionChat,
                            (string) $conversation->id,
                            AiCallPurpose::ReceptionReply,
                            $turnId,
                            $skipIds,
                            $conversation->contact_id,
                        ),
                        new ReceptionToolEventContextData(
                            conversation_id: (string) $conversation->id,
                            turn_id: $turnId,
                            definitions: $toolset->event_definitions,
                        ),
                    ),
                );
            } catch (AllModelsExhaustedException $e) {
                Log::warning('[reception] 所有候选模型均失败，执行 AI 不可用处理', [
                    'conversation_id' => $this->conversationId,
                    'turn_id' => $turnId,
                    'candidate_count' => count($e->candidateErrors),
                    'candidate_errors' => array_map(
                        static fn (\Throwable $error): array => [
                            'class' => $error::class,
                            'message' => $error->getMessage(),
                        ],
                        $e->candidateErrors,
                    ),
                ]);
                $aiUnavailable->handle($conversation, $runtime->ai_unavailable_notice);

                return;
            }

            if ($outcome->isPreempted()) {
                Log::info('[reception] AI 接待轮次已被新访客消息抢占', [
                    'conversation_id' => $this->conversationId,
                    'turn_id' => $turnId,
                ]);

                return;
            }

            // respond 工具在循环中已即时投递的过渡气泡也计入本轮产出。
            $delivered = $delivery->delivered();
            $deliveredMessageCount = $delivery->count();

            if ($outcome->text !== '') {
                // 模型执行期间可能发生人工接管或 handoff，最终文本只投递给仍由 AI 接待的会话。
                $conversation->refresh();
                if ($conversation->inbox_status !== ConversationInboxStatus::AiHandling) {
                    Log::info('[reception] 跳过 AI 最终回复：会话已不在 AI 接待状态', [
                        'conversation_id' => $this->conversationId,
                        'inbox_status' => $conversation->inbox_status->value,
                    ]);
                } elseif ($preemption->isPreempted($this->conversationId, $turnId)) {
                    // 新访客消息不改变 inbox_status，投递前单独检查抢占信号。
                    Log::info('[reception] 跳过 AI 最终回复：投递前检测到新访客消息抢占', [
                        'conversation_id' => $this->conversationId,
                        'turn_id' => $turnId,
                    ]);
                } else {
                    $this->warnIfUngroundedGuidance($outcome->text, $groundingProbe);
                    $append->handle($conversation, $outcome->text, $quotedId, $runtime->reception_plan_version_id, $turnId);
                    $delivered = true;
                    $deliveredMessageCount++;
                }
            }

            // 本轮已向访客产出过消息（最终文本或 respond 过渡气泡）即置幂等标记，避免 release 重投重复投递。
            if ($delivered) {
                Cache::put($this->completionKey(), true, now()->addMinutes(30));
                Log::info('[reception] AI 接待轮次回复已投递', [
                    'conversation_id' => $this->conversationId,
                    'turn_id' => $turnId,
                    'delivered_message_count' => $deliveredMessageCount,
                ]);
            } elseif ($outcome->text === '') {
                Log::info('[reception] 本轮未向访客产出任何消息', [
                    'conversation_id' => $this->conversationId,
                    'turn_id' => $turnId,
                ]);
            }
        } catch (\Throwable $exception) {
            $releaseActivity = false;

            throw $exception;
        } finally {
            if ($releaseActivity) {
                $realtimeNotifier->aiTurnStopped($this->conversationId, $this->activityId);
            }
            $preemption->endTurn($this->conversationId, $turnId);
        }
    }

    /**
     * 本轮幂等键：由会话 ID + 聚合文本 + 触发消息 ID 派生，重复投递的同一 job 必然同键。
     */
    private function completionKey(): string
    {
        return 'reception:turn:done:'.$this->conversationId.':'
            .sha1($this->aggregatedText.'|'.implode(',', $this->messageIds).'|'.implode(',', $this->mediaMessageIds));
    }

    /**
     * 按候选模型能力构建本轮文本、媒体内容块和文字占位。
     */
    private function buildNewMessage(
        MultimodalMessageBuilder $messageBuilder,
        RuntimeModelCandidateData $modelCandidate,
        Collection $mediaAttachments,
    ): UserMessage {
        $blocks = [];

        if ($this->aggregatedText !== '') {
            $blocks[] = new TextContent($this->aggregatedText);
        }

        foreach ($messageBuilder->attachmentBlocks(
            $mediaAttachments,
            $modelCandidate->supports_image_input,
            $modelCandidate->supports_video_input,
            $modelCandidate->ai_model_id,
        ) as $block) {
            $blocks[] = $block;
        }

        return new UserMessage($blocks === [] ? '' : $blocks);
    }

    /**
     * 按消息顺序加载本轮媒体附件。
     *
     * @return Collection<int, Attachment>
     */
    private function loadMediaAttachments(): Collection
    {
        if ($this->mediaMessageIds === []) {
            return collect();
        }

        return ConversationMessage::query()
            ->where('conversation_id', $this->conversationId)
            ->whereIn('id', $this->mediaMessageIds)
            ->with('attachments.storageProfile')
            ->orderBy('seq_no')
            ->get()
            ->flatMap(
                static fn (ConversationMessage $message): Collection => $message->attachments,
            )
            ->values();
    }

    /**
     * 记录媒体能力导致的候选顺序变化。
     *
     * @param  list<RuntimeModelCandidateData>  $original
     * @param  list<RuntimeModelCandidateData>  $prioritized
     */
    private function logCandidatePriorityChange(
        array $original,
        array $prioritized,
        bool $requiresImageInput,
        bool $requiresVideoInput,
        string $turnId,
    ): void {
        $originalIds = array_map(
            static fn (RuntimeModelCandidateData $candidate): string => $candidate->ai_model_id,
            $original,
        );
        $prioritizedIds = array_map(
            static fn (RuntimeModelCandidateData $candidate): string => $candidate->ai_model_id,
            $prioritized,
        );

        if ($originalIds === $prioritizedIds) {
            return;
        }

        Log::info('[reception] 按媒体输入能力调整模型候选顺序', [
            'conversation_id' => $this->conversationId,
            'turn_id' => $turnId,
            'requires_image_input' => $requiresImageInput,
            'requires_video_input' => $requiresVideoInput,
            'original_ai_model_ids' => $originalIds,
            'prioritized_ai_model_ids' => $prioritizedIds,
        ]);
    }

    /**
     * 记录任务失败、投递不可用提示，并在提示广播后释放活动租约。
     */
    public function failed(?\Throwable $exception): void
    {
        try {
            Log::error('[reception] AI 接待轮次彻底失败', [
                'conversation_id' => $this->conversationId,
                'text_message_count' => count($this->messageIds),
                'media_message_count' => count($this->mediaMessageIds),
                'error_class' => $exception !== null ? $exception::class : null,
                'error' => $exception?->getMessage(),
            ]);

            $conversation = Conversation::query()->find($this->conversationId);
            if ($conversation === null) {
                return;
            }

            $runtime = app(LoadReceptionRuntimeAction::class)->handle($this->conversationId);
            if (! $runtime->available) {
                return;
            }

            app(HandleAiUnavailableAction::class)->handle($conversation, $runtime->ai_unavailable_notice);
        } finally {
            app(ReceptionRealtimeNotifier::class)->aiTurnStopped($this->conversationId, $this->activityId);
        }
    }

    /**
     * 记录知识检索零命中但回复包含操作指引的情况。
     */
    private function warnIfUngroundedGuidance(string $reply, ReceptionGroundingProbe $groundingProbe): void
    {
        if (! $groundingProbe->isUngroundedGuidance($reply)) {
            return;
        }

        Log::warning('[reception] 疑似无据操作指引：knowledge_search 零命中却输出了操作步骤，可能是模型编造的操作路径', [
            'conversation_id' => $this->conversationId,
            'searched' => $groundingProbe->searched(),
            'reply_excerpt' => Str::limit($reply, 200),
        ]);
    }
}
