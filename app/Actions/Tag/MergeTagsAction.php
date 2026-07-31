<?php

namespace App\Actions\Tag;

use App\Data\CurrentUserContextData;
use App\Data\Tag\FormMergeTagData;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 把多个标签合并到目标标签，并迁移关联关系。
 */
class MergeTagsAction
{
    use AsAction;

    public function handle(FormMergeTagData $data): Tag
    {
        return DB::transaction(function () use ($data): Tag {
            if ($data->target_tag_id === $data->merged_tag_id) {
                throw ValidationException::withMessages([
                    'merged_tag_id' => __('tag.errors.merge_same_tag'),
                ]);
            }

            $targetTag = Tag::query()

                ->findOrFail($data->target_tag_id);

            $mergedTag = Tag::query()

                ->findOrFail($data->merged_tag_id);

            if ($mergedTag->is_locked) {
                throw ValidationException::withMessages([
                    'merged_tag_id' => __('tag.errors.locked_cannot_be_merged'),
                ]);
            }

            $existingTargetContactIds = DB::table('contact_tag_assignments')
                ->where('tag_id', $targetTag->id)
                ->pluck('contact_id');

            DB::table('contact_tag_assignments')
                ->where('tag_id', $mergedTag->id)
                ->whereNotIn('contact_id', $existingTargetContactIds)
                ->update(['tag_id' => $targetTag->id]);

            DB::table('contact_tag_assignments')
                ->where('tag_id', $mergedTag->id)
                ->delete();

            // 标签同时可挂在会话上（conversation 维度），会话侧关联同样要迁移，否则合并会话标签时这些关联会被静默丢弃。
            $existingTargetConversationIds = DB::table('conversation_tag_assignments')
                ->where('tag_id', $targetTag->id)
                ->pluck('conversation_id');

            DB::table('conversation_tag_assignments')
                ->where('tag_id', $mergedTag->id)
                ->whereNotIn('conversation_id', $existingTargetConversationIds)
                ->update(['tag_id' => $targetTag->id]);

            DB::table('conversation_tag_assignments')
                ->where('tag_id', $mergedTag->id)
                ->delete();

            $mergedTag->delete();

            $affectedContactIds = DB::table('contact_tag_assignments')
                ->where('tag_id', $targetTag->id)
                ->pluck('contact_id');

            if ($affectedContactIds->isNotEmpty()) {
                Contact::query()
                    ->whereIn('id', $affectedContactIds)
                    ->get()
                    ->each
                    ->searchable();
            }

            return $targetTag->fresh();
        });
    }

    public function asController(Request $request)
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $data = FormMergeTagData::from($request);
        $this->handle($data);

        return back();
    }
}
