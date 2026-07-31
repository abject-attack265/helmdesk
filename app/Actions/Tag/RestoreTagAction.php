<?php

namespace App\Actions\Tag;

use App\Data\CurrentUserContextData;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\TagGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 恢复已删除标签。
 */
class RestoreTagAction
{
    use AsAction;

    public function handle(string $id): Tag
    {
        $tag = Tag::query()

            ->onlyTrashed()
            ->findOrFail($id);

        $conflict = Tag::query()

            ->where('tag_group_id', $tag->tag_group_id)
            ->where('normalized_name', $tag->normalized_name)
            ->whereNull('deleted_at')
            ->exists();

        if ($conflict) {
            throw ValidationException::withMessages([
                'tag' => __('tag.errors.restore_name_conflict'),
            ]);
        }

        // 标签必须有可见的归属组：若其标签组在标签删除后被一并删掉，恢复标签时连带恢复该组，
        // 否则恢复出来的标签会因为组不可见而在管理页消失。
        $group = TagGroup::withTrashed()

            ->find($tag->tag_group_id);

        if ($group !== null && $group->trashed()) {
            $group->restore();
        }

        $tag->restore();

        $affectedContactIds = DB::table('contact_tag_assignments')
            ->where('tag_id', $tag->id)
            ->pluck('contact_id');

        if ($affectedContactIds->isNotEmpty()) {
            Contact::query()
                ->whereIn('id', $affectedContactIds)
                ->get()
                ->each
                ->searchable();
        }

        return $tag;
    }

    public function asController(Request $request, string $id)
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $this->handle($id);

        return back();
    }
}
