<?php

namespace App\Data\Contact;

use App\Data\EnumOptionData;
use App\Data\Tag\TagOptionData;
use App\Enums\IdentityType;
use App\Models\Contact;
use App\Models\ContactIdentity;
use Spatie\LaravelData\Data;

/**
 * 联系人列表项数据。
 * 显示在 resources/js/pages/contacts/Index.vue、Trash.vue 和 ContactDetailDrawer.vue 的表格或列表中，只包含列表渲染和快捷操作需要的字段。
 */
class ListContactItemData extends Data
{
    public function __construct(
        public string $id,
        public ?string $name,
        public EnumOptionData $type,
        public EnumOptionData $source,
        public string $avatar_url,
        public ?string $primary_email,
        public ?string $primary_phone,
        /** @var string[] 外部 ID（external_id 身份）值列表，含各渠道 */
        public array $external_ids,
        public bool $is_important,
        public ?string $last_seen_at,
        /** @var TagOptionData[] */
        public array $tags,
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
            external_ids: $contact->identities
                ->where('type', IdentityType::ExternalId)
                ->sortBy('created_at')
                ->map(fn (ContactIdentity $identity) => $identity->display_value ?: $identity->value)
                ->filter()
                ->values()
                ->all(),
            is_important: $contact->is_important,
            last_seen_at: $contact->last_seen_at?->toIso8601String(),
            tags: $contact->tags
                ->whereNull('deleted_at')
                ->values()
                ->map(fn ($tag) => TagOptionData::fromModel($tag))
                ->all(),
        );
    }
}
