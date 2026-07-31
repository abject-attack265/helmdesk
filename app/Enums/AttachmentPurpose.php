<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 附件用途，决定上传校验规则和对象存储路径。
 */
enum AttachmentPurpose: string implements LabeledEnum
{
    case Avatar = 'avatar';
    case ChannelIcon = 'channel_icon';
    case ConversationImage = 'conversation_image';
    case ConversationFile = 'conversation_file';
    case KnowledgeDocument = 'knowledge_document';
    case Import = 'import';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Avatar => __('attachments.purposes.avatar'),
            self::ChannelIcon => __('attachments.purposes.channel_icon'),
            self::ConversationImage => __('attachments.purposes.conversation_image'),
            self::ConversationFile => __('attachments.purposes.conversation_file'),
            self::KnowledgeDocument => __('attachments.purposes.knowledge_document'),
            self::Import => __('attachments.purposes.import'),
            self::Other => __('attachments.purposes.other'),
        };
    }
}
