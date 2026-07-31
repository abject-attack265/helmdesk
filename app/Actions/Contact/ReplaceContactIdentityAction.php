<?php

namespace App\Actions\Contact;

use App\Data\Contact\FormReplaceContactIdentityData;
use App\Data\CurrentUserContextData;
use App\Enums\IdentityType;
use App\Models\Contact;
use App\Models\ContactActivityLog;
use App\Models\ContactIdentity;
use App\Models\User;
use App\Services\Contact\ContactActivityLogger;
use App\Services\Contact\ContactIdentityNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * 替换联系人身份标识，处理冲突和主标识更新。
 */
class ReplaceContactIdentityAction
{
    use AsAction;

    public function handle(
        string $contactId,
        string $identityId,
        FormReplaceContactIdentityData $data,
        ?User $actor = null,
    ): ContactIdentity {
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

        if ($identity->type === IdentityType::Email
            && ! filter_var($data->value, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'value' => __('contact.invalid_email'),
            ]);
        }

        $normalizedValue = ContactIdentityNormalizer::validateAndNormalize($identity->type, $data->value);

        if ($normalizedValue === $identity->value) {
            return $identity;
        }

        $existing = ContactIdentity::query()

            ->where('type', $identity->type)
            ->where('namespace', $identity->namespace)
            ->where('value', $normalizedValue)
            ->whereNull('deleted_at')
            ->with('contact')
            ->first();

        if ($existing) {
            $contactName = $existing->contact->name ?? $existing->contact->id;

            throw ValidationException::withMessages([
                'value' => __('contact.identity_already_exists', [
                    'type' => $identity->type->label(),
                    'name' => $contactName,
                ]),
            ]);
        }

        return DB::transaction(function () use ($contact, $identity, $normalizedValue, $actor) {
            $replacement = ContactIdentity::query()->create([
                'contact_id' => $contact->id,
                'type' => $identity->type,
                'namespace' => $identity->namespace,
                'value' => $normalizedValue,
                'display_value' => ContactIdentityNormalizer::buildDisplayValue($identity->type, $normalizedValue),
                'created_at' => $identity->created_at,
            ]);

            $identity->delete();
            $contact->syncPrimaryFields();
            ContactActivityLogger::record(
                $contact,
                ContactActivityLog::ACTION_IDENTITY_REPLACED,
                $actor,
                payload: [
                    'identity_type' => $identity->type->value,
                    'old_value' => $identity->value,
                    'new_value' => $normalizedValue,
                    'identity_values' => [$identity->value, $normalizedValue],
                ],
            );

            return $replacement;
        });
    }

    public function asController(
        Request $request,
        string $contactId,
        string $identityId,
    ): Response {
        $ctx = CurrentUserContextData::fromRequest($request);
        $data = FormReplaceContactIdentityData::from($request);

        $this->handle($contactId, $identityId, $data, $request->user());

        return back();
    }
}
