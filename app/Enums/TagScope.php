<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 标签适用维度，决定标签组及其下标签作用于「会话」还是「联系人」。
 * 维度挂在标签组上，标签经由所属组继承维度；查询/打标签按此区分两类词表，互不串用。
 */
enum TagScope: string implements LabeledEnum
{
    case Conversation = 'conversation';
    case Contact = 'contact';

    public function label(): string
    {
        return match ($this) {
            self::Conversation => __('tag.scopes.conversation'),
            self::Contact => __('tag.scopes.contact'),
        };
    }
}
