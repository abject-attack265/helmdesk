<?php

namespace App\Actions\Contact;

use App\Data\Contact\ContactActivityLogData;
use App\Data\Contact\ContactDetailData;
use App\Data\CurrentUserContextData;
use App\Data\CustomAttribute\ContactAttributeFieldData;
use App\Models\AttributeDefinition;
use App\Models\Contact;
use App\Models\ContactActivityLog;
use App\Models\ContactAttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 展示联系人详情及标签、自定义属性等侧栏数据。
 */
class ShowContactDetailAction
{
    use AsAction;

    /**
     * 返回联系人详情、活动日志和可展示的自定义属性。
     */
    public function handle(string $contactId, bool $includeTrashed = false): ContactDetailData
    {
        $contactQuery = Contact::query()
            ->when($includeTrashed, fn ($query) => $query->withTrashed())
            ->with([
                'identities' => fn ($query) => $includeTrashed ? $query->withTrashed() : $query,
            ]);

        $contact = $contactQuery->findOrFail($contactId);

        $activityLogs = ContactActivityLog::query()
            ->where('contact_id', $contactId)
            ->with('actor')
            ->latest('created_at')
            ->get()
            ->map(fn (ContactActivityLog $activityLog) => ContactActivityLogData::fromModel($activityLog));

        $customAttributes = $this->buildCustomAttributeFields($contact);

        return ContactDetailData::fromModel($contact, $activityLogs, $customAttributes);
    }

    /**
     * 组装有效属性定义和联系人仍有值的归档属性定义。
     *
     * @return ContactAttributeFieldData[]
     */
    private function buildCustomAttributeFields(Contact $contact): array
    {
        $activeDefinitions = AttributeDefinition::query()
            ->active()
            ->ordered()
            ->get();

        $contactValues = ContactAttributeValue::query()
            ->where('contact_id', $contact->id)
            ->with('definition')
            ->get()
            ->keyBy('definition_id');

        $archivedDefinitionsWithValues = $contactValues
            ->map(fn (ContactAttributeValue $value) => $value->definition)
            ->filter(fn (AttributeDefinition $definition) => $definition->trashed());

        $allDefinitions = $activeDefinitions->merge($archivedDefinitionsWithValues);

        $fields = [];

        foreach ($allDefinitions as $definition) {
            $value = $contactValues->get($definition->id);

            $fields[] = new ContactAttributeFieldData(
                definition_id: $definition->id,
                key: $definition->key,
                name: $definition->name,
                description: $definition->description,
                type: $definition->type->value,
                type_label: $definition->type->label(),
                config: $definition->config,
                value: $value?->value(),
                source: $value?->source?->value,
                source_label: $value?->source?->label(),
                deleted_at: $definition->deleted_at?->toIso8601String(),
                is_editable: ! $definition->trashed(),
            );
        }

        return $fields;
    }

    /**
     * 解析详情查询选项并返回 JSON 数据。
     */
    public function asController(Request $request, string $id): JsonResponse
    {
        CurrentUserContextData::fromRequest($request);
        $includeTrashed = $request->boolean('include_trashed');

        return response()->json($this->handle($id, $includeTrashed)->toArray());
    }
}
