<?php

namespace App\Data\Contact;

use App\Data\EnumOptionData;
use App\Models\Contact;
use Spatie\LaravelData\Data;

/**
 * 回收站联系人数据。
 * 显示在 resources/js/pages/contacts/Index.vue、Trash.vue 和 ContactDetailDrawer.vue 的回收站列表里，用于恢复、彻底删除和基础信息展示。
 */
class TrashContactItemData extends Data
{
    public function __construct(
        public string $id,
        public ?string $name,
        public EnumOptionData $type,
        public EnumOptionData $source,
        public string $avatar_url,
        public ?string $primary_email,
        public ?string $primary_phone,
        public string $created_at,
        public ?string $deleted_at,
    ) {}

    public static function fromModel(Contact $contact): self
    {
        return new self(
            id: $contact->id,
            name: $contact->name,
            type: EnumOptionData::fromEnum($contact->type),
            source: EnumOptionData::fromEnum($contact->source),
            avatar_url: $contact->avatar_url,
            primary_email: $contact->primary_email,
            primary_phone: $contact->primary_phone,
            created_at: $contact->created_at?->toIso8601String() ?? now()->toIso8601String(),
            deleted_at: $contact->deleted_at?->toIso8601String(),
        );
    }
}
