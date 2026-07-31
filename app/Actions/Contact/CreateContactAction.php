<?php

namespace App\Actions\Contact;

use App\Data\Contact\FormCreateContactData;
use App\Data\CurrentUserContextData;
use App\Enums\ContactSource;
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
 * 创建联系人，并按需要同时写入身份标识。
 */
class CreateContactAction
{
    use AsAction;

    public function handle(FormCreateContactData $data, ?User $actor = null): Contact
    {
        $email = $data->email
            ? ContactIdentityNormalizer::validateAndNormalize(IdentityType::Email, $data->email, 'email')
            : null;
        $phone = $data->phone
            ? ContactIdentityNormalizer::validateAndNormalize(IdentityType::Phone, $data->phone, 'phone')
            : null;

        if (! $email && ! $phone) {
            throw ValidationException::withMessages([
                'email' => __('contact.at_least_one_identity'),
            ]);
        }

        $this->checkDuplicateIdentity(IdentityType::Email, $email);
        $this->checkDuplicateIdentity(IdentityType::Phone, $phone);

        return DB::transaction(function () use ($data, $email, $phone, $actor) {
            $contact = Contact::query()->create([
                'type' => ContactType::Contact,
                'source' => ContactSource::Manual,
                'name' => $data->name ? trim($data->name) : null,
                'avatar_url' => Contact::DEFAULT_AVATAR_URL,
            ]);

            if ($email) {
                ContactIdentity::query()->create([
                    'contact_id' => $contact->id,
                    'type' => IdentityType::Email,
                    'namespace' => '',
                    'value' => $email,
                    'display_value' => ContactIdentityNormalizer::buildDisplayValue(IdentityType::Email, $email),
                ]);
            }

            if ($phone) {
                ContactIdentity::query()->create([
                    'contact_id' => $contact->id,
                    'type' => IdentityType::Phone,
                    'namespace' => '',
                    'value' => $phone,
                    'display_value' => ContactIdentityNormalizer::buildDisplayValue(IdentityType::Phone, $phone),
                ]);
            }

            $contact->syncPrimaryFields();
            ContactActivityLogger::record(
                $contact,
                ContactActivityLog::ACTION_CREATED,
                $actor,
                payload: [
                    'origin' => 'manual',
                    'name' => $contact->name,
                    'source' => $contact->source->value,
                    'type' => $contact->type->value,
                    'identity_values' => array_values(array_filter([$email, $phone])),
                ],
            );

            return $contact;
        });
    }

    public function asController(Request $request): Response
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $data = FormCreateContactData::from($request);

        $this->handle($data, $request->user());

        return back();
    }

    private function checkDuplicateIdentity(IdentityType $type, ?string $value): void
    {
        if (! $value) {
            return;
        }

        $existing = ContactIdentity::query()

            ->where('type', $type)
            ->where('namespace', '')
            ->where('value', $value)
            ->whereNull('deleted_at')
            ->with('contact')
            ->first();

        if ($existing) {
            $fieldName = $type === IdentityType::Email ? 'email' : 'phone';
            $contactName = $existing->contact->name ?? $existing->contact->id;

            throw ValidationException::withMessages([
                $fieldName => __('contact.identity_already_exists', [
                    'type' => $type->label(),
                    'name' => $contactName,
                ]),
            ]);
        }
    }
}
