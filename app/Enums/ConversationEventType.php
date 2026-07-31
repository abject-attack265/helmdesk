<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 会话事件类型，用于时间线里的系统事件。
 */
enum ConversationEventType: string implements LabeledEnum
{
    case Created = 'created';
    case AssignmentChanged = 'assignment_changed';
    case HandoffRequested = 'handoff_requested';
    case StatusChanged = 'status_changed';
    case ReceptionTurnStarted = 'reception_turn_started';
    case ReceptionToolCalled = 'reception_tool_called';
    case ReceptionTurnEnded = 'reception_turn_ended';
    case FeedbackReceived = 'feedback_received';

    public function label(): string
    {
        return match ($this) {
            self::Created => __('conversation.event_types.created'),
            self::AssignmentChanged => __('conversation.event_types.assignment_changed'),
            self::HandoffRequested => __('conversation.event_types.handoff_requested'),
            self::StatusChanged => __('conversation.event_types.status_changed'),
            self::ReceptionTurnStarted => __('conversation.event_types.reception_turn_started'),
            self::ReceptionToolCalled => __('conversation.event_types.reception_tool_called'),
            self::ReceptionTurnEnded => __('conversation.event_types.reception_turn_ended'),
            self::FeedbackReceived => __('conversation.event_types.feedback_received'),
        };
    }
}
