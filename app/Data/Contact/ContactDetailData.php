<?php

namespace App\Data\Contact;

use App\Data\CustomAttribute\ContactAttributeFieldData;
use App\Data\EnumOptionData;
use App\Data\Tag\ContactTagData;
use App\Models\Contact;
use Illuminate\Support\Collection;
use Spatie\LaravelData\Data;

/**
 * 联系人详情数据。
 * 由后端组装后传给 resources/js/pages/contacts/Index.vue、Trash.vue 和 ContactDetailDrawer.vue，用于页面展示、抽屉详情或局部交互状态。
 */
class ContactDetailData extends Data
{
    public function __construct(
        public string $id,
        public ?string $name,
        public EnumOptionData $type,
        public EnumOptionData $source,
        public string $avatar_url,
        public ?string $primary_email,
        public ?string $primary_phone,
        public ?string $locale,
        public ?string $timezone,
        public ?string $country,
        public ?string $city,
        /** @var array<string, mixed>|null */
        public ?array $ai_context,
        public ?string $note,
        public bool $is_important,
        public ?string $important_at,
        public ?string $last_seen_at,
        public string $created_at,
        public ?string $deleted_at,
        /** @var ContactIdentityData[] */
        public array $identities,
        /** @var ContactTagData[] */
        public array $tags,
        /** @var ContactActivityLogData[] */
        public array $activity_logs,
        /** @var ContactAttributeFieldData[] */
        public array $custom_attributes = [],
    ) {}

    /**
     * @param  Collection<int, ContactActivityLogData>|array<int, ContactActivityLogData>  $activity_logs
     * @param  array<int, ContactAttributeFieldData>  $custom_attributes
     */
    public static function fromModel(Contact $contact, Collection|array $activity_logs = [], array $custom_attributes = []): self
    {
        $contact->loadMissing(['identities', 'tags']);

        return new self(
            id: $contact->id,
            name: $contact->name,
            type: EnumOptionData::fromEnum($contact->type),
            source: EnumOptionData::fromEnum($contact->source),
            avatar_url: $contact->avatar_url,
            primary_email: $contact->primary_email,
            primary_phone: $contact->primary_phone,
            locale: $contact->locale,
            timezone: $contact->timezone,
            country: $contact->country,
            city: $contact->city,
            ai_context: $contact->ai_context,
            note: $contact->note,
            is_important: $contact->is_important,
            important_at: $contact->important_at?->toIso8601String(),
            last_seen_at: $contact->last_seen_at?->toIso8601String(),
            created_at: $contact->created_at?->toIso8601String() ?? '',
            deleted_at: $contact->deleted_at?->toIso8601String(),
            identities: $contact->identities
                ->map(fn ($identity) => ContactIdentityData::fromModel($identity))
                ->all(),
            tags: $contact->tags
                ->whereNull('deleted_at')
                ->values()
                ->map(fn ($tag) => ContactTagData::fromModel($tag))
                ->all(),
            activity_logs: $activity_logs instanceof Collection ? $activity_logs->all() : $activity_logs,
            custom_attributes: $custom_attributes,
        );
    }
}
