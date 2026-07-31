<?php

namespace App\Actions\Tag;

use App\Data\CurrentUserContextData;
use App\Data\Tag\FormCreateTagGroupData;
use App\Models\TagGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 创建标签组；scope 决定该组及组内标签作用于会话还是联系人。
 */
class CreateTagGroupAction
{
    use AsAction;

    /**
     * 校验组名在应用内唯一后创建标签组。
     */
    public function handle(FormCreateTagGroupData $data, ?User $actor = null): TagGroup
    {
        $name = trim($data->name);
        $normalizedName = mb_strtolower($name);

        $exists = TagGroup::query()

            ->where('normalized_name', $normalizedName)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => __('tag.errors.group_name_exists'),
            ]);
        }

        return TagGroup::query()->create([
            'name' => $name,
            'scope' => $data->scope,
            'created_by_user_id' => $actor?->id,
        ]);
    }

    /**
     * 接收新建标签组表单提交并返回上一页。
     */
    public function asController(Request $request)
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $data = FormCreateTagGroupData::from($request);
        $this->handle($data, $request->user());

        return back();
    }
}
