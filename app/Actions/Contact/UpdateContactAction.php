<?php

namespace App\Actions\Contact;

use App\Data\Contact\FormUpdateContactData;
use App\Data\CurrentUserContextData;
use App\Enums\ContactType;
use App\Models\Contact;
use App\Models\ContactActivityLog;
use App\Models\User;
use App\Services\Contact\ContactActivityLogger;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * 更新联系人资料、备注和地区信息。
 */
class UpdateContactAction
{
    use AsAction;

    public function handle(string $contactId, FormUpdateContactData $data, ?User $actor = null): Contact
    {
        $contact = Contact::query()

            ->findOrFail($contactId);

        $updates = [];
        $fieldChanges = [];

        foreach (['name', 'note', 'country', 'city'] as $field) {
            if ($data->{$field} === null) {
                continue;
            }

            $normalizedValue = trim($data->{$field}) ?: null;

            if ($normalizedValue !== $contact->{$field}) {
                $updates[$field] = $normalizedValue;
                $fieldChanges[$field] = [
                    'old' => $contact->{$field},
                    'new' => $normalizedValue,
                ];
            }
        }

        if ($data->type !== null) {
            $normalizedType = ContactType::from($data->type);

            if ($normalizedType !== $contact->type) {
                $updates['type'] = $normalizedType;
                $fieldChanges['type'] = [
                    'old' => $contact->type->value,
                    'new' => $normalizedType->value,
                ];
            }
        }

        if (! empty($updates)) {
            $contact->update($updates);
            ContactActivityLogger::record(
                $contact,
                ContactActivityLog::ACTION_UPDATED,
                $actor,
                payload: [
                    'field_changes' => $fieldChanges,
                ],
            );
        }

        return $contact;
    }

    public function asController(Request $request, string $id): Response
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $data = FormUpdateContactData::from($request);

        $this->handle($id, $data, $request->user());

        return back();
    }
}
