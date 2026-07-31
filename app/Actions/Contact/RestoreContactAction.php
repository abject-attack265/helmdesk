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
 * 恢复已删除联系人。
 */
class RestoreContactAction
{
    use AsAction;

    public function handle(string $contactId, ?User $actor = null): Contact
    {
        $contact = Contact::withTrashed()

            ->findOrFail($contactId);

        if (! $contact->trashed()) {
            return $contact;
        }

        $trashedIdentities = ContactIdentity::withTrashed()
            ->where('contact_id', $contact->id)
            ->whereNotNull('deleted_at')
            ->get();

        $conflicts = [];
        foreach ($trashedIdentities as $identity) {
            $activeConflict = ContactIdentity::query()

                ->where('type', $identity->type)
                ->where('namespace', $identity->namespace)
                ->where('value', $identity->value)
                ->whereNull('deleted_at')
                ->with('contact')
                ->first();

            if ($activeConflict) {
                $conflicts[] = __('contact.restore_conflict', [
                    'type' => $identity->type->label(),
                    'value' => $identity->display_value ?? $identity->value,
                    'name' => $activeConflict->contact->name ?? $activeConflict->contact->id,
                ]);
            }
        }

        if (! empty($conflicts)) {
            throw ValidationException::withMessages([
                'contact' => $conflicts,
            ]);
        }

        return DB::transaction(function () use ($contact, $actor) {
            $contact->restore();

            ContactIdentity::withTrashed()
                ->where('contact_id', $contact->id)
                ->whereNotNull('deleted_at')
                ->each(fn ($identity) => $identity->restore());

            $contact->syncPrimaryFields();
            ContactActivityLogger::record($contact, ContactActivityLog::ACTION_RESTORED, $actor);

            return $contact;
        });
    }

    public function asController(Request $request, string $id): Response
    {
        $ctx = CurrentUserContextData::fromRequest($request);

        $this->handle($id, $request->user());

        return back();
    }
}
