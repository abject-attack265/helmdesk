<?php

namespace App\Actions\Contact;

use App\Actions\Conversation\MergeContactConversationThreadsAction;
use App\Data\Contact\FormMergeContactsData;
use App\Data\CurrentUserContextData;
use App\Enums\AttributeType;
use App\Enums\AttributeValueSource;
use App\Enums\ContactType;
use App\Enums\ConversationStatus;
use App\Enums\IdentityType;
use App\Exceptions\BusinessException;
use App\Models\AttributeDefinition;
use App\Models\Contact;
use App\Models\ContactActivityLog;
use App\Models\ContactAttributeValue;
use App\Models\ContactIdentity;
use App\Models\Conversation;
use App\Models\Tag;
use App\Models\User;
use App\Services\Contact\ContactActivityLogger;
use App\Services\Contact\ContactAiContext;
use App\Services\Contact\ContactIdentityNormalizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * 合并两个联系人，并迁移身份、标签、属性和会话关联。
 */
class MergeContactsAction
{
    use AsAction;

    /**
     * 将被合并联系人的资料和业务关联迁移到目标联系人。
     */
    public function handle(string $targetContactId, string $mergedContactId, ?User $actor = null): Contact
    {
        if ($targetContactId === $mergedContactId) {
            throw new InvalidArgumentException('Cannot merge a contact with itself.');
        }

        return DB::transaction(function () use ($targetContactId, $mergedContactId, $actor) {
            [$target, $merged] = $this->lockContacts($targetContactId, $mergedContactId);
            $this->lockContactConversations($target, $merged);

            $this->ensureConversationOwnershipCanBeMerged($target, $merged);

            $mergedIdentities = $merged->identities()->get();
            $this->mergeAttributes($target, $merged, $mergedIdentities);

            $mergedCustomAttributes = $this->mergeCustomAttributes($target, $merged);

            $identitySnapshots = $mergedIdentities->map(fn (ContactIdentity $i) => [
                'id' => $i->id,
                'type' => $i->type->value,
                'value' => $i->value,
                'namespace' => $i->namespace,
            ])->all();

            $attributeSnapshot = [
                'name' => $merged->name,
                'source' => $merged->source->value,
                'type' => $merged->type->value,
                'locale' => $merged->locale,
                'timezone' => $merged->timezone,
                'country' => $merged->country,
                'city' => $merged->city,
                'note' => $merged->note,
                'ai_context' => $merged->ai_context,
                'is_important' => $merged->is_important,
                'important_at' => $merged->important_at?->toIso8601String(),
                'important_source' => $merged->important_source,
            ];

            ContactIdentity::query()
                ->where('contact_id', $merged->id)
                ->update(['contact_id' => $target->id]);

            $identityValues = $mergedIdentities->pluck('value')->all();

            $logPayload = [
                'related_contact_name' => $merged->name,
                'identity_values' => $identityValues,
                'identity_snapshots' => $identitySnapshots,
                'merged_attributes' => $attributeSnapshot,
            ];

            if (! empty($mergedCustomAttributes)) {
                $logPayload['merged_custom_attributes'] = $mergedCustomAttributes;
            }

            ContactAttributeValue::query()
                ->where('contact_id', $merged->id)
                ->delete();

            ContactActivityLogger::record(
                contact: $target,
                action: ContactActivityLog::ACTION_MERGED_INTO_CURRENT,
                actor: $actor,
                payload: $logPayload,
            );

            ContactActivityLogger::record(
                contact: $merged,
                action: ContactActivityLog::ACTION_MERGED_INTO_OTHER,
                actor: $actor,
                payload: [
                    'related_contact_name' => $target->name,
                    'identity_values' => $identityValues,
                    'identity_snapshots' => $identitySnapshots,
                    'merged_attributes' => $attributeSnapshot,
                ],
            );

            $this->mergeTags($target, $merged);
            $this->reassignConversationLinks($target, $merged);

            $merged->delete();

            $target->syncPrimaryFields();

            return $target->fresh();
        });
    }

    /**
     * 按 ID 顺序锁定参与合并的联系人，并按目标与来源顺序返回。
     *
     * @return array{0: Contact, 1: Contact}
     */
    private function lockContacts(string $targetContactId, string $mergedContactId): array
    {
        $contacts = Contact::query()
            ->whereIn('id', [$targetContactId, $mergedContactId])
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (Contact $contact) => (string) $contact->id);

        $target = $contacts->get($targetContactId);
        $merged = $contacts->get($mergedContactId);

        if (! $target instanceof Contact || ! $merged instanceof Contact) {
            throw (new ModelNotFoundException)->setModel(Contact::class, [
                $targetContactId,
                $mergedContactId,
            ]);
        }

        return [$target, $merged];
    }

    /**
     * 锁定双方全部会话，保证冲突校验与归属迁移使用同一状态。
     */
    private function lockContactConversations(Contact $target, Contact $merged): void
    {
        Conversation::query()
            ->whereIn('contact_id', [$target->id, $merged->id])
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id']);
    }

    /**
     * 使用被合并联系人的资料填充目标联系人的空字段和可累积状态。
     *
     * @param  Collection<int, ContactIdentity>  $mergedIdentities
     */
    private function mergeAttributes(Contact $target, Contact $merged, Collection $mergedIdentities): void
    {
        $fillableNullFields = ['name', 'locale', 'timezone', 'country', 'city', 'note'];

        foreach ($fillableNullFields as $field) {
            if ($target->{$field} === null && $merged->{$field} !== null) {
                $target->{$field} = $merged->{$field};
            }
        }

        if ($merged->last_seen_at !== null) {
            if ($target->last_seen_at === null || $merged->last_seen_at->isAfter($target->last_seen_at)) {
                $target->last_seen_at = $merged->last_seen_at;
            }
        }

        if (! $target->is_important && $merged->is_important) {
            $target->is_important = true;
            $target->important_at = $merged->important_at;
            $target->important_by_user_id = $merged->important_by_user_id;
            $target->important_source = $merged->important_source;
        }

        $target->ai_context = ContactAiContext::merge($target->ai_context, $merged->ai_context);

        $shouldPromoteTarget = $target->type === ContactType::Contact
            || $merged->type === ContactType::Contact
            || $target->identities()
                ->whereIn('type', [
                    IdentityType::Email,
                    IdentityType::Phone,
                    IdentityType::ExternalId,
                    IdentityType::ChannelAccount,
                ])
                ->exists()
            || $mergedIdentities->contains(
                fn (ContactIdentity $identity) => ContactIdentityNormalizer::promotesContactType($identity->type)
            );

        if ($shouldPromoteTarget && $target->type !== ContactType::Contact) {
            $target->type = ContactType::Contact;
        }

        $target->saveQuietly();
    }

    /**
     * 按属性类型规则合并自定义属性并返回实际写入的字段快照。
     *
     * @return list<array{key: string, old: mixed, merged: mixed, value: mixed}>
     */
    private function mergeCustomAttributes(Contact $target, Contact $merged): array
    {
        $targetValues = ContactAttributeValue::query()
            ->where('contact_id', $target->id)
            ->with('definition')
            ->get()
            ->keyBy('definition_id');

        $mergedValues = ContactAttributeValue::query()
            ->where('contact_id', $merged->id)
            ->with('definition')
            ->get()
            ->keyBy('definition_id');

        $snapshot = [];

        foreach ($mergedValues as $definitionId => $mergedVal) {
            $definition = $mergedVal->definition;

            $targetVal = $targetValues->get($definitionId);
            $mergedRaw = $mergedVal->value();
            $targetRaw = $targetVal?->value();

            $resultValue = $this->mergeCustomAttributeValue($definition, $targetRaw, $mergedRaw);
            $hasChanged = $targetVal === null || $targetRaw !== $resultValue;

            if ($resultValue === null || ! $hasChanged) {
                continue;
            }

            ContactAttributeValue::query()->updateOrCreate(
                [
                    'contact_id' => $target->id,
                    'definition_id' => $definitionId,
                ],
                [
                    'value_json' => ['value' => $resultValue],
                    'source' => AttributeValueSource::Merge,
                    'updated_by_user_id' => null,
                ],
            );

            $snapshot[] = [
                'key' => $definition->key,
                'old' => $targetRaw,
                'merged' => $mergedRaw,
                'value' => $resultValue,
            ];
        }

        return $snapshot;
    }

    /**
     * 计算单个自定义属性合并后的值。
     */
    private function mergeCustomAttributeValue(AttributeDefinition $definition, mixed $targetValue, mixed $mergedValue): mixed
    {
        if ($definition->type === AttributeType::MultiSelect) {
            $targetArr = $targetValue ?? [];
            $mergedArr = $mergedValue ?? [];
            $union = array_merge($targetArr, $mergedArr) |> array_unique(...) |> array_values(...);

            return ! empty($union) ? $union : null;
        }

        if ($definition->type === AttributeType::Boolean) {
            if ($targetValue !== null) {
                return $targetValue;
            }

            return $mergedValue;
        }

        if ($targetValue !== null && $targetValue !== '') {
            return $targetValue;
        }

        return $mergedValue;
    }

    /**
     * 归并收件箱线程，并迁移会话及时间线、评价和访问轨迹中的联系人列。
     */
    private function reassignConversationLinks(Contact $target, Contact $merged): void
    {
        MergeContactConversationThreadsAction::run($target, $merged);

        $tables = [
            'conversations',
            'conversation_timeline_entries',
            'conversation_ratings',
            'conversation_page_views',
        ];

        foreach ($tables as $table) {
            DB::table($table)
                ->where('contact_id', $merged->id)
                ->update(['contact_id' => $target->id]);
        }
    }

    /**
     * 阻止同一渠道的两条开放会话在联系人迁移后违反唯一归属约束。
     */
    private function ensureConversationOwnershipCanBeMerged(Contact $target, Contact $merged): void
    {
        $conflictingConversation = Conversation::query()
            ->where('contact_id', $merged->id)
            ->where('status', ConversationStatus::Open)
            ->whereNotNull('channel_id')
            ->whereIn('channel_id', Conversation::query()
                ->select('channel_id')
                ->where('contact_id', $target->id)
                ->where('status', ConversationStatus::Open)
                ->whereNotNull('channel_id'))
            ->first(['id', 'channel_id']);

        if ($conflictingConversation === null) {
            return;
        }

        Log::info('联系人合并存在开放会话归属冲突', [
            'target_contact_id' => $target->id,
            'merged_contact_id' => $merged->id,
            'channel_id' => $conflictingConversation->channel_id,
            'conversation_id' => $conflictingConversation->id,
        ]);

        throw new BusinessException(__('contact.merge_open_conversation_conflict'));
    }

    /**
     * 合并有效标签并清理被合并联系人的标签关联。
     */
    private function mergeTags(Contact $target, Contact $merged): void
    {
        $activeTagIds = Tag::query()
            ->pluck('id');

        $existingTagIds = DB::table('contact_tag_assignments')
            ->where('contact_id', $target->id)
            ->pluck('tag_id');

        $mergedAssignments = DB::table('contact_tag_assignments')
            ->where('contact_id', $merged->id)
            ->whereIn('tag_id', $activeTagIds)
            ->get();

        foreach ($mergedAssignments as $assignment) {
            if (! $existingTagIds->contains($assignment->tag_id)) {
                DB::table('contact_tag_assignments')->insert([
                    'tag_id' => $assignment->tag_id,
                    'contact_id' => $target->id,
                    'assigned_by_user_id' => $assignment->assigned_by_user_id,
                    'source' => $assignment->source,
                    'created_at' => $assignment->created_at,
                ]);
            }
        }

        DB::table('contact_tag_assignments')
            ->where('contact_id', $merged->id)
            ->delete();
    }

    /**
     * 校验联系人合并请求并返回联系人列表页面。
     */
    public function asController(Request $request): Response
    {
        CurrentUserContextData::fromRequest($request);
        $data = FormMergeContactsData::from($request);

        $this->handle(
            $data->target_contact_id,
            $data->merged_contact_id,
            $request->user(),
        );

        return back();
    }
}
