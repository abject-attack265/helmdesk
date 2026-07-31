<?php

namespace App\Actions\Conversation;

use App\Data\Conversation\ConversationEventDisplayData;
use App\Enums\ConversationEventSemanticType;
use App\Enums\ConversationEventTone;
use App\Enums\ConversationEventType;
use App\Enums\ConversationInboxStatus;
use App\Enums\ConversationStatus;
use App\Services\Conversation\ConversationEventPayloadDecoder;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * 将会话事件 payload 转换为客服可读的时间线活动展示数据。
 */
class BuildConversationEventDisplayAction
{
    use AsAction;

    /**
     * 根据事件类型和 payload 生成结构化展示数据。
     *
     * @param  array<string, string>  $userNamesById
     */
    public function handle(object $row, array $userNamesById): ConversationEventDisplayData
    {
        $eventType = ConversationEventType::tryFrom((string) $row->event_type)
            ?? throw new RuntimeException('Unknown conversation event type: '.(string) $row->event_type);
        $payload = ConversationEventPayloadDecoder::decode($row->payload);

        return match ($eventType) {
            ConversationEventType::Created => $this->created($row, $payload, $userNamesById),
            ConversationEventType::HandoffRequested => $this->handoffRequested($payload),
            ConversationEventType::AssignmentChanged => $this->assignmentChanged($row, $payload, $userNamesById),
            ConversationEventType::StatusChanged => $this->statusChanged($row, $payload, $userNamesById),
            ConversationEventType::ReceptionToolCalled => $this->receptionToolCalled($payload),
            ConversationEventType::FeedbackReceived => $this->feedbackReceived($payload),
            ConversationEventType::ReceptionTurnStarted,
            ConversationEventType::ReceptionTurnEnded => throw new RuntimeException('Reception runtime boundary event reached timeline display.'),
        };
    }

    /**
     * 构建访客满意度评价事件展示。
     *
     * @param  array<string, mixed>  $payload
     */
    private function feedbackReceived(array $payload): ConversationEventDisplayData
    {
        $score = $this->requiredPayloadString($payload, 'score');

        $summary = match ($score) {
            'positive' => __('conversation.event_displays.feedback_received.positive'),
            'negative' => __('conversation.event_displays.feedback_received.negative'),
            default => throw new RuntimeException('Unknown conversation rating score: '.$score),
        };

        $comment = $payload['comment'] ?? null;
        $detail = is_string($comment) && trim($comment) !== '' ? trim($comment) : null;

        return new ConversationEventDisplayData(
            summary: $summary,
            detail: $detail,
            semantic_type: ConversationEventSemanticType::Conversation,
            tone: ConversationEventTone::Muted,
        );
    }

    /**
     * 构建会话创建事件展示。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $userNamesById
     */
    private function created(object $row, array $payload, array $userNamesById): ConversationEventDisplayData
    {
        $source = $this->requiredPayloadString($payload, 'source');

        $summary = match ($source) {
            'reception' => __('conversation.event_displays.created.reception'),
            'manual' => __('conversation.event_displays.created.manual', [
                'actor' => $this->actorNameOrSystem($row, $userNamesById),
            ]),
            default => throw new RuntimeException('Unknown conversation created source: '.$source),
        };

        return new ConversationEventDisplayData(
            summary: $summary,
            detail: null,
            semantic_type: ConversationEventSemanticType::Conversation,
            tone: ConversationEventTone::Muted,
        );
    }

    /**
     * 构建 AI 请求人工介入事件展示。
     *
     * @param  array<string, mixed>  $payload
     */
    private function handoffRequested(array $payload): ConversationEventDisplayData
    {
        // reason 由 AI 自由生成，不是闭合枚举；未知值显示通用转人工事件。
        $reason = $this->requiredPayloadString($payload, 'reason');
        [$summary, $tone] = match ($reason) {
            'user_requested' => [
                __('conversation.event_displays.handoff_requested.user_requested'),
                ConversationEventTone::Normal,
            ],
            'ai_requested' => [
                __('conversation.event_displays.handoff_requested.ai_requested'),
                ConversationEventTone::Important,
            ],
            'low_confidence' => [
                __('conversation.event_displays.handoff_requested.low_confidence'),
                ConversationEventTone::Normal,
            ],
            'tool_failure' => [
                __('conversation.event_displays.handoff_requested.tool_failure'),
                ConversationEventTone::Warning,
            ],
            'policy_required' => [
                __('conversation.event_displays.handoff_requested.policy_required'),
                ConversationEventTone::Normal,
            ],
            'ai_unavailable' => [
                __('conversation.event_displays.handoff_requested.ai_unavailable'),
                ConversationEventTone::Warning,
            ],
            default => [
                __('conversation.event_displays.handoff_requested.default'),
                ConversationEventTone::Normal,
            ],
        };

        return new ConversationEventDisplayData(
            summary: $summary,
            detail: null,
            semantic_type: ConversationEventSemanticType::BotAction,
            tone: $tone,
        );
    }

    /**
     * 构建分配变更事件展示。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $userNamesById
     */
    private function assignmentChanged(object $row, array $payload, array $userNamesById): ConversationEventDisplayData
    {
        $source = $this->requiredPayloadString($payload, 'source');

        return match ($source) {
            'claim' => $this->claimAssignment($row, $userNamesById),
            'reply' => $this->replyAssignment($row, $userNamesById),
            'transfer_to_human' => $this->humanTransferAssignment($row, $userNamesById),
            'takeover' => $this->takeoverAssignment($row, $payload, $userNamesById),
            'transfer_to_teammate' => $this->teammateTransferAssignment($row, $payload, $userNamesById),
            'release_to_ai' => $this->releaseToAiAssignment($row, $payload, $userNamesById),
            default => throw new RuntimeException('Unknown assignment_changed source: '.$source),
        };
    }

    /**
     * 构建普通接单事件。
     *
     * @param  array<string, string>  $userNamesById
     */
    private function claimAssignment(object $row, array $userNamesById): ConversationEventDisplayData
    {
        $actor = $this->requiredActorName($row, $userNamesById);

        return new ConversationEventDisplayData(
            summary: __('conversation.event_displays.assignment_changed.claim', ['actor' => $actor]),
            detail: null,
            semantic_type: ConversationEventSemanticType::UserAction,
            tone: ConversationEventTone::Normal,
        );
    }

    /**
     * 构建回复时自动接管事件。
     *
     * @param  array<string, string>  $userNamesById
     */
    private function replyAssignment(object $row, array $userNamesById): ConversationEventDisplayData
    {
        $actor = $this->requiredActorName($row, $userNamesById);

        return new ConversationEventDisplayData(
            summary: __('conversation.event_displays.assignment_changed.reply', ['actor' => $actor]),
            detail: null,
            semantic_type: ConversationEventSemanticType::UserAction,
            tone: ConversationEventTone::Normal,
        );
    }

    /**
     * 构建 AI 转人工后的接管事件。
     *
     * @param  array<string, string>  $userNamesById
     */
    private function humanTransferAssignment(object $row, array $userNamesById): ConversationEventDisplayData
    {
        $actor = $this->requiredActorName($row, $userNamesById);

        return new ConversationEventDisplayData(
            summary: __('conversation.event_displays.assignment_changed.transfer_to_human', ['actor' => $actor]),
            detail: null,
            semantic_type: ConversationEventSemanticType::UserAction,
            tone: ConversationEventTone::Normal,
        );
    }

    /**
     * 构建从其他客服接管事件。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $userNamesById
     */
    private function takeoverAssignment(object $row, array $payload, array $userNamesById): ConversationEventDisplayData
    {
        $actor = $this->requiredActorName($row, $userNamesById);
        $previousUser = $this->requiredPayloadUserName($payload, 'previous_user_id', $userNamesById);

        return new ConversationEventDisplayData(
            summary: __('conversation.event_displays.assignment_changed.takeover', ['actor' => $actor, 'previous_user' => $previousUser]),
            detail: null,
            semantic_type: ConversationEventSemanticType::UserAction,
            tone: ConversationEventTone::Normal,
        );
    }

    /**
     * 构建客服之间转接事件。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $userNamesById
     */
    private function teammateTransferAssignment(object $row, array $payload, array $userNamesById): ConversationEventDisplayData
    {
        $actor = $this->requiredActorName($row, $userNamesById);
        $target = $this->requiredPayloadUserName($payload, 'user_id', $userNamesById);

        return new ConversationEventDisplayData(
            summary: __('conversation.event_displays.assignment_changed.transfer_to_teammate', ['actor' => $actor, 'target' => $target]),
            detail: null,
            semantic_type: ConversationEventSemanticType::UserAction,
            tone: ConversationEventTone::Normal,
        );
    }

    /**
     * 构建交回 AI 或待接队列事件。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $userNamesById
     */
    private function releaseToAiAssignment(object $row, array $payload, array $userNamesById): ConversationEventDisplayData
    {
        $actor = $this->requiredActorName($row, $userNamesById);
        $inboxStatus = ConversationInboxStatus::tryFrom($this->requiredPayloadString($payload, 'inbox_status'))
            ?? throw new RuntimeException('Unknown conversation inbox status.');
        $summaryKey = match ($inboxStatus) {
            ConversationInboxStatus::AiHandling => 'conversation.event_displays.assignment_changed.release_to_ai',
            ConversationInboxStatus::TeammatePending => 'conversation.event_displays.assignment_changed.release_to_queue',
            ConversationInboxStatus::TeammateHandling => throw new RuntimeException('Unexpected release target inbox status.'),
        };

        return new ConversationEventDisplayData(
            summary: __($summaryKey, ['actor' => $actor]),
            detail: null,
            semantic_type: ConversationEventSemanticType::UserAction,
            tone: ConversationEventTone::Muted,
        );
    }

    /**
     * 构建会话状态变更事件。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $userNamesById
     */
    private function statusChanged(object $row, array $payload, array $userNamesById): ConversationEventDisplayData
    {
        $actor = $this->actorNameOrSystem($row, $userNamesById);
        $status = ConversationStatus::tryFrom($this->requiredPayloadString($payload, 'status'))
            ?? throw new RuntimeException('Unknown conversation status.');

        if ($status === ConversationStatus::Open) {
            return new ConversationEventDisplayData(
                summary: __('conversation.event_displays.status_changed.open', ['actor' => $actor]),
                detail: null,
                semantic_type: ConversationEventSemanticType::StatusChange,
                tone: ConversationEventTone::Normal,
            );
        }

        return new ConversationEventDisplayData(
            summary: __('conversation.event_displays.status_changed.closed', ['actor' => $actor]),
            detail: null,
            semantic_type: ConversationEventSemanticType::StatusChange,
            tone: ConversationEventTone::Muted,
            facts: [],
        );
    }

    /**
     * 构建 AI 工具调用事件。
     *
     * @param  array<string, mixed>  $payload
     */
    private function receptionToolCalled(array $payload): ConversationEventDisplayData
    {
        $displayName = $this->requiredPayloadString($payload, 'display_name');
        $status = $this->requiredPayloadString($payload, 'status');
        if (! in_array($status, ['success', 'failed'], true)) {
            throw new RuntimeException('Unknown reception tool event status: '.$status);
        }

        return new ConversationEventDisplayData(
            summary: __('conversation.event_displays.reception_tool_called.'.$status, [
                'tool' => $displayName,
            ]),
            detail: null,
            semantic_type: ConversationEventSemanticType::ToolCall,
            tone: $status === 'failed' ? ConversationEventTone::Warning : ConversationEventTone::Muted,
            facts: [],
        );
    }

    /**
     * 读取必需的 payload 字符串字段。
     *
     * @param  array<string, mixed>  $payload
     */
    private function requiredPayloadString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new RuntimeException('Missing conversation event payload field: '.$key);
        }

        return $value;
    }

    /**
     * 读取 payload 中的用户 ID 并解析成员名称。
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $userNamesById
     */
    private function requiredPayloadUserName(array $payload, string $key, array $userNamesById): string
    {
        $userId = $this->requiredPayloadString($payload, $key);

        return $userNamesById[$userId] ?? throw new RuntimeException('Unknown app user in conversation event: '.$userId);
    }

    /**
     * 解析事件 actor 名称；无 actor 时返回系统。
     *
     * @param  array<string, string>  $userNamesById
     */
    private function actorNameOrSystem(object $row, array $userNamesById): string
    {
        if ($row->actor_user_id === null) {
            return __('conversation.event_displays.actors.system');
        }

        return $this->requiredActorName($row, $userNamesById);
    }

    /**
     * 解析事件 actor 名称，缺失时显性失败。
     *
     * @param  array<string, string>  $userNamesById
     */
    private function requiredActorName(object $row, array $userNamesById): string
    {
        if ($row->actor_user_id === null) {
            throw new RuntimeException('Conversation event actor_user_id is required.');
        }

        $userId = (string) $row->actor_user_id;

        return $userNamesById[$userId] ?? throw new RuntimeException('Unknown app actor in conversation event: '.$userId);
    }
}
