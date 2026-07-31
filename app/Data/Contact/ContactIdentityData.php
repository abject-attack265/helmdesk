<?php

namespace App\Data\Contact;

use App\Data\EnumOptionData;
use App\Models\ContactIdentity;
use Spatie\LaravelData\Data;

/**
 * 联系人身份标识数据。
 * 由后端组装后传给 resources/js/pages/contacts/Index.vue、Trash.vue 和 ContactDetailDrawer.vue，用于页面展示、抽屉详情或局部交互状态。
 */
class ContactIdentityData extends Data
{
    public function __construct(
        public string $id,
        public EnumOptionData $type,
        public string $namespace,
        public ?string $display_value,
        public string $created_at,
    ) {}

    public static function fromModel(ContactIdentity $identity): self
    {
        return new self(
            id: $identity->id,
            type: EnumOptionData::fromEnum($identity->type),
            namespace: $identity->namespace,
            display_value: $identity->display_value,
            created_at: $identity->created_at?->toIso8601String() ?? '',
        );
    }
}
