<?php

namespace App\Actions\Contact;

use App\Data\Contact\FormCreateContactIdentityData;
use App\Data\CurrentUserContextData;
use App\Enums\ContactType;
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
 * 给联系人新增邮箱、手机号等身份标识。
 */
class CreateContactIdentityAction
{
    use AsAction;

    public function handle(
        string $contactId,
        FormCreateContactIdentityData $data,
        ?User $actor = null,
    ): ContactIdentity {
        $contact = Contact::query()

            ->findOrFail($contactId);

        $type = IdentityType::from($data->type);
        $value = ContactIdentityNormalizer::validateAndNormalize($type, $data->value);
        $namespace = $data->namespace ?? '';

        if ($type->requiresNamespace() && $namespace === '') {
            throw ValidationException::withMessages([
                'namespace' => __('contact.namespace_required_for_external_id'),
            ]);
        }

        $existing = ContactIdentity::query()

            ->where('type', $type)
            ->where('namespace', $namespace)
            ->where('value', $value)
            ->whereNull('deleted_at')
            ->with('contact')
            ->first();

        if ($existing) {
            $contactName = $existing->contact->name ?? $existing->contact->id;

            throw ValidationException::withMessages([
                'value' => __('contact.identity_already_exists', [
                    'type' => $type->label(),
                    'name' => $contactName,
                ]),
            ]);
        }

        $shouldPromote = ContactIdentityNormalizer::promotesContactType($type);

        return DB::transaction(function () use ($contact, $type, $value, $namespace, $shouldPromote, $actor) {
            $wasVisitor = $contact->type === ContactType::Visitor;

            $identity = ContactIdentity::query()->create([
                'contact_id' => $contact->id,
                'type' => $type,
                'namespace' => $namespace,
                'value' => $value,
                'display_value' => ContactIdentityNormalizer::buildDisplayValue($type, $value),
            ]);

            if ($shouldPromote && $contact->type === ContactType::Visitor) {
                $contact->type = ContactType::Contact;
                $contact->saveQuietly();
            }

            $contact->syncPrimaryFields();
            ContactActivityLogger::record(
                $contact,
                ContactActivityLog::ACTION_IDENTITY_ADDED,
                $actor,
                payload: [
                    'identity_type' => $type->value,
                    'identity_value' => $value,
                    'identity_values' => [$value],
                    'promoted_to_contact' => $shouldPromote && $wasVisitor,
                ],
            );

            return $identity;
        });
    }

    public function asController(Request $request, string $contactId): Response
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $data = FormCreateContactIdentityData::from($request);

        $this->handle($contactId, $data, $request->user());

        return back();
    }
}
