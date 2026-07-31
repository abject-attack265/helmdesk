<?php

namespace App\Actions\Tag;

use App\Data\CurrentUserContextData;
use App\Data\Tag\FormUpdateTagData;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 更新标签名称、颜色、说明，并允许在同维度的标签组之间移动。
 */
class UpdateTagAction
{
    use AsAction;

    /**
     * 更新标签字段并在同维度组之间移动；改名后刷新关联联系人搜索索引。
     */
    public function handle(string $id, FormUpdateTagData $data, ?User $actor = null): Tag
    {
        $tag = Tag::query()

            ->with('tagGroup')
            ->findOrFail($id);

        $targetGroup = TagGroup::query()

            ->find($data->tag_group_id);

        if ($targetGroup === null) {
            throw ValidationException::withMessages([
                'tag_group_id' => __('tag.errors.group_required'),
            ]);
        }

        // 标签只能在同一适用维度的组之间移动，避免会话标签被挪进联系人组（反之亦然）。
        if ($targetGroup->scope !== $tag->tagGroup->scope) {
            throw ValidationException::withMessages([
                'tag_group_id' => __('tag.errors.group_scope_mismatch'),
            ]);
        }

        $name = trim($data->name);
        $normalizedName = mb_strtolower($name);

        $exists = Tag::query()

            ->where('tag_group_id', $targetGroup->id)
            ->where('normalized_name', $normalizedName)
            ->where('id', '!=', $tag->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => __('tag.errors.name_exists'),
            ]);
        }

        $shouldRefreshContacts = $tag->name !== $name;

        $tag->update([
            'tag_group_id' => $targetGroup->id,
            'name' => $name,
            'color' => $data->color,
            'description' => $data->description,
            'updated_by_user_id' => $actor?->id,
        ]);

        if ($shouldRefreshContacts) {
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
        }

        return $tag;
    }

    public function asController(Request $request, string $id)
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $data = FormUpdateTagData::from($request);
        $this->handle($id, $data, $request->user());

        return back();
    }
}
