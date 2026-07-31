<?php

namespace App\Enums;

use App\Contracts\LabeledEnum;

/**
 * 联系人列表页签类型，用于区分全部、联系人和访客视图。
 */
enum ContactListType: string implements LabeledEnum
{
    case All = 'all';
    case Contacts = 'contacts';
    case Visitors = 'visitors';

    public function label(): string
    {
        return match ($this) {
            self::All => __('contact.list_types.all'),
            self::Contacts => __('contact.list_types.contacts'),
            self::Visitors => __('contact.list_types.visitors'),
        };
    }

    /**
     * 将列表页签映射为联系人模型类型筛选条件；全部页签返回 null 表示不过滤类型。
     */
    public function contactType(): ?ContactType
    {
        return match ($this) {
            self::All => null,
            self::Contacts => ContactType::Contact,
            self::Visitors => ContactType::Visitor,
        };
    }
}
