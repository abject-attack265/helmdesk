<?php

namespace App\Actions\Tag;

use App\Data\CurrentUserContextData;
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
 * 从联系人身上移除指定标签。
 */
class DetachContactTagAction
{
    use AsAction;

    public function handle(string $contactId, string $tagId, ?User $actor = null): void
    {
        $contact = Contact::query()

            ->findOrFail($contactId);

        $tag = Tag::query()

            ->withTrashed()
            ->findOrFail($tagId);

        DB::transaction(function () use ($contact, $tag, $actor): void {
            $deletedAssignments = DB::table('contact_tag_assignments')
                ->where('tag_id', $tag->id)
                ->where('contact_id', $contact->id)
                ->delete();

            if ($deletedAssignments === 0) {
                return;
            }

            ContactActivityLogger::record(
                contact: $contact,
                action: ContactActivityLog::ACTION_TAG_DETACHED,
                actor: $actor,
                payload: ['tag_id' => $tag->id, 'tag_name' => $tag->name],
            );

            $contact->searchable();
        });
    }

    public function asController(Request $request, string $id, string $tagId): JsonResponse
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $this->handle($id, $tagId, $request->user());

        return response()->json(['success' => true]);
    }
}
