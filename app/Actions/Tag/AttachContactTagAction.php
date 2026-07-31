<?php

namespace App\Actions\Tag;

use App\Data\CurrentUserContextData;
use App\Data\Tag\FormAttachContactTagData;
use App\Models\Contact;
use App\Models\ContactActivityLog;
use App\Models\Tag;
use App\Models\User;
use App\Services\Contact\ContactActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 给联系人打上指定标签。
 */
class AttachContactTagAction
{
    use AsAction;

    public function handle(string $contactId, FormAttachContactTagData $data, ?User $actor = null): void
    {
        $contact = Contact::query()

            ->findOrFail($contactId);

        $tag = Tag::query()

            ->findOrFail($data->tag_id);

        $alreadyAttached = DB::table('contact_tag_assignments')
            ->where('tag_id', $tag->id)
            ->where('contact_id', $contact->id)
            ->exists();

        if ($alreadyAttached) {
            return;
        }

        DB::transaction(function () use ($contact, $tag, $actor): void {
            DB::table('contact_tag_assignments')->insert([
                'tag_id' => $tag->id,
                'contact_id' => $contact->id,
                'assigned_by_user_id' => $actor?->id,
                'source' => 'manual',
                'created_at' => now(),
            ]);

            ContactActivityLogger::record(
                contact: $contact,
                action: ContactActivityLog::ACTION_TAG_ATTACHED,
                actor: $actor,
                payload: ['tag_id' => $tag->id, 'tag_name' => $tag->name],
            );

            $contact->searchable();
        });
    }

    public function asController(Request $request, string $id): JsonResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $data = FormAttachContactTagData::from($request);
        $this->handle($id, $data, $request->user());

        return response()->json(['success' => true]);
    }
}
