<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 联系人类型，区分匿名访客和已沉淀联系人。
 */
enum ContactType: string implements LabeledEnum
{
    case Visitor = 'visitor';
    case Contact = 'contact';

    public function label(): string
    {
        return match ($this) {
            self::Visitor => __('contact.types.visitor'),
            self::Contact => __('contact.types.contact'),
        };
    }
}
