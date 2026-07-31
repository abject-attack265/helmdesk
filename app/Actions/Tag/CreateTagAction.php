<?php

namespace App\Actions\Tag;

use App\Data\CurrentUserContextData;
use App\Data\Tag\FormCreateTagData;
use App\Enums\TagSource;
use App\Models\Tag;
use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 在指定标签组下创建应用标签；标签维度经由所属组继承。
 */
class CreateTagAction
{
    use AsAction;

    /**
     * 校验标签组归属与名称唯一后，在该组下创建标签。
     */
    public function handle(FormCreateTagData $data, ?User $actor = null): Tag
    {
        $group = TagGroup::query()

            ->find($data->tag_group_id);

        if ($group === null) {
            throw ValidationException::withMessages([
                'tag_group_id' => __('tag.errors.group_required'),
            ]);
        }

        $name = trim($data->name);
        $normalizedName = mb_strtolower($name);

        $exists = Tag::query()

            ->where('tag_group_id', $group->id)
            ->where('normalized_name', $normalizedName)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => __('tag.errors.name_exists'),
            ]);
        }

        return Tag::query()->create([
            'tag_group_id' => $group->id,
            'name' => $name,
            'color' => $data->color,
            'description' => $data->description,
            'source' => TagSource::Manual,
            'created_by_user_id' => $actor?->id,
        ]);
    }

    public function asController(Request $request)
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $data = FormCreateTagData::from($request);
        $this->handle($data, $request->user());

        return back();
    }
}
