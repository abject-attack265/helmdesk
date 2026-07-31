<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * AI 调用日志的业务用途，用于筛选和排查不同类型的模型调用。
 */
enum AiCallPurpose: string implements LabeledEnum
{
    case ReceptionReply = 'reception_reply';
    case ConversationSummary = 'conversation_summary';
    case ConversationTags = 'conversation_tags';
    case ConversationSubject = 'conversation_subject';
    case ContactHandoffBrief = 'contact_handoff_brief';
    case ContactAttributes = 'contact_attributes';
    case ReplyPolish = 'reply_polish';
    case Assistant = 'assistant';
    case ExperienceExtraction = 'experience_extraction';
    case Translation = 'translation';

    /**
     * 返回 AI 调用用途的显示文案。
     */
    public function label(): string
    {
        return match ($this) {
            self::ReceptionReply => __('ai.call_purposes.reception_reply'),
            self::ConversationSummary => __('ai.call_purposes.conversation_summary'),
            self::ConversationTags => __('ai.call_purposes.conversation_tags'),
            self::ConversationSubject => __('ai.call_purposes.conversation_subject'),
            self::ContactHandoffBrief => __('ai.call_purposes.contact_handoff_brief'),
            self::ContactAttributes => __('ai.call_purposes.contact_attributes'),
            self::ReplyPolish => __('ai.call_purposes.reply_polish'),
            self::Assistant => __('ai.call_purposes.assistant'),
            self::ExperienceExtraction => __('ai.call_purposes.experience_extraction'),
            self::Translation => __('ai.call_purposes.translation'),
        };
    }
}
