<?php

namespace App\Actions\Contact;

use App\Data\CurrentUserContextData;
use App\Models\Contact;
use App\Models\ContactActivityLog;
use App\Models\User;
use App\Services\Contact\ContactActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * 软删除联系人并保留后续恢复能力。
 */
class DeleteContactAction
{
    use AsAction;

    public function handle(string $contactId, ?User $actor = null): void
    {
        $contact = Contact::query()

            ->findOrFail($contactId);

        DB::transaction(function () use ($contact, $actor) {
            $contact->identities()->each(fn ($identity) => $identity->delete());
            $contact->delete();
            ContactActivityLogger::record($contact, ContactActivityLog::ACTION_DELETED, $actor);
        });
    }

    public function asController(Request $request, string $id): Response
    {
        $ctx = CurrentUserContextData::fromRequest($request);

        $this->handle($id, $request->user());

        return back();
    }
}
