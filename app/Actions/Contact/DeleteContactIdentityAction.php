<?php

namespace App\Actions\Contact;

use App\Data\CurrentUserContextData;
use App\Models\Contact;
use App\Models\ContactActivityLog;
use App\Models\ContactIdentity;
use App\Models\User;
use App\Services\Contact\ContactActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * 删除联系人下的一个身份标识。
 */
class DeleteContactIdentityAction
{
    use AsAction;

    public function handle(
        string $contactId,
        string $identityId,
        ?User $actor = null,
    ): void {
        $contact = Contact::query()

            ->findOrFail($contactId);

        $identity = ContactIdentity::query()

            ->where('contact_id', $contact->id)
            ->findOrFail($identityId);

        if (! $identity->type->supportsManualManagement()) {
            throw ValidationException::withMessages([
                'identity' => __('contact.identity_manual_management_not_supported'),
            ]);
        }

        DB::transaction(function () use ($contact, $identity, $actor) {
            $identity->delete();
            $contact->syncPrimaryFields();
            ContactActivityLogger::record(
                $contact,
                ContactActivityLog::ACTION_IDENTITY_DELETED,
                $actor,
                payload: [
                    'identity_type' => $identity->type->value,
                    'identity_value' => $identity->value,
                    'identity_values' => [$identity->value],
                ],
            );
        });
    }

    public function asController(
        Request $request,
        string $contactId,
        string $identityId,
    ): Response {
        $ctx = CurrentUserContextData::fromRequest($request);

        $this->handle($contactId, $identityId, $request->user());

        return back();
    }
}
