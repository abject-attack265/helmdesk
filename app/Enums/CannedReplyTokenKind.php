<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 快捷回复模版变量的命名空间分类。
 */
enum CannedReplyTokenKind: string implements LabeledEnum
{
    case Contact = 'contact';
    case Conversation = 'conversation';
    case Teammate = 'teammate';
    case Instance = 'app';

    public function label(): string
    {
        return match ($this) {
            self::Contact => __('canned_reply.token_kinds.contact'),
            self::Conversation => __('canned_reply.token_kinds.conversation'),
            self::Teammate => __('canned_reply.token_kinds.teammate'),
            self::Instance => __('canned_reply.token_kinds.app'),
        };
    }

    /**
     * 返回支持静态解析的变量命名空间。
     *
     * @return array<int, self>
     */
    public static function staticCases(): array
    {
        return [self::Contact, self::Conversation, self::Teammate, self::Instance];
    }
}
