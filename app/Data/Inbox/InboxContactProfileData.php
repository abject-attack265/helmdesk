<?php

namespace App\Data\Inbox;

use App\Data\Contact\ContactHandoffBriefData;
use App\Data\Conversation\ContactConversationTagAggregateData;
use App\Data\CustomAttribute\ContactAttributeFieldData;
use App\Data\Tag\ContactTagData;
use App\Enums\ContactSource;
use App\Enums\ContactType;
use App\Enums\IdentityType;
use App\Models\Contact;
use App\Models\ContactIdentity;
use Spatie\LaravelData\Data;

/**
 * 收件箱右侧联系人资料。
 * 显示在 pages/inbox/InboxContextPanel.vue，包含头像、身份和最近上下文。
 */
class InboxContactProfileData extends Data
{
    /**
     * 创建收件箱联系人资料和接手简报数据。
     *
     * @param  string[]  $external_ids
     * @param  ContactTagData[]  $tags
     * @param  ContactAttributeFieldData[]  $custom_attributes
     * @param  ContactConversationTagAggregateData[]  $conversation_tag_aggregates
     */
    public function __construct(
        public string $id,
        public ContactType $type,
        public string $type_label,
        public ContactSource $source,
        public string $source_label,
        public ?string $name,
        public string $avatar_url,
        public ?string $primary_email,
        public ?string $primary_email_identity_id,
        public ?string $primary_phone,
        public ?string $primary_phone_identity_id,
        public array $external_ids,
        public ?string $locale,
        public ?string $timezone,
        public ?string $country,
        public ?string $city,
        public ?string $note,
        public ?ContactHandoffBriefData $handoff_brief,
        public bool $is_important,
        public ?string $important_at,
        public ?string $last_seen_at,
        public ?string $created_at,
        public array $tags,
        public array $custom_attributes = [],
        public array $conversation_tag_aggregates = [],
    ) {}

    /**
     * 从联系人模型构建收件箱资料数据。
     *
     * @param  ContactAttributeFieldData[]  $customAttributes
     * @param  ContactConversationTagAggregateData[]  $conversationTagAggregates
     */
    public static function fromModel(Contact $contact, array $customAttributes = [], array $conversationTagAggregates = []): self
    {
        $contact->loadMissing(['identities', 'tags']);
        $primaryEmailIdentity = self::primaryIdentity($contact, IdentityType::Email);
        $primaryPhoneIdentity = self::primaryIdentity($contact, IdentityType::Phone);

        return new self(
            id: $contact->id,
            type: $contact->type,
            type_label: $contact->type->label(),
            source: $contact->source,
            source_label: $contact->source->label(),
            name: $contact->name,
            avatar_url: $contact->avatar_url,
            primary_email: $contact->primary_email,
            primary_email_identity_id: $primaryEmailIdentity?->id,
            primary_phone: $contact->primary_phone,
            primary_phone_identity_id: $primaryPhoneIdentity?->id,
            external_ids: self::externalIds($contact),
            locale: $contact->locale,
            timezone: $contact->timezone,
            country: $contact->country,
            city: $contact->city,
            note: $contact->note,
            handoff_brief: ContactHandoffBriefData::fromContext($contact->ai_context),
            is_important: $contact->is_important,
            important_at: $contact->important_at?->toIso8601String(),
            last_seen_at: $contact->last_seen_at?->toIso8601String(),
            created_at: $contact->created_at?->toIso8601String(),
            tags: $contact->tags
                ->whereNull('deleted_at')
                ->values()
                ->map(fn ($tag) => ContactTagData::fromModel($tag))
                ->all(),
            custom_attributes: $customAttributes,
            conversation_tag_aggregates: $conversationTagAggregates,
        );
    }

    /**
     * 获取指定类型最早创建的联系人身份。
     */
    private static function primaryIdentity(Contact $contact, IdentityType $type): ?ContactIdentity
    {
        return $contact->identities
            ->where('type', $type)
            ->sortBy('created_at')
            ->first();
    }

    /**
     * 联系人的全部外部 ID（external_id 身份）值，按创建时间排序、去空。
     *
     * @return string[]
     */
    private static function externalIds(Contact $contact): array
    {
        return $contact->identities
            ->where('type', IdentityType::ExternalId)
            ->sortBy('created_at')
            ->map(fn (ContactIdentity $identity) => $identity->display_value ?: $identity->value)
            ->filter()
            ->values()
            ->all();
    }
}
