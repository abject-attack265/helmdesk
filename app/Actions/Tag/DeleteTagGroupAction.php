<?php

namespace App\Actions\Tag;

use App\Data\CurrentUserContextData;
use App\Exceptions\BusinessException;
use App\Models\Tag;
use App\Models\TagGroup;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 软删除标签组；组内仍有未删除标签时拒绝删除，避免标签失去归属。
 */
class DeleteTagGroupAction
{
    use AsAction;

    /**
     * 组内仍有未删除标签时拒绝删除，否则软删除标签组。
     */
    public function handle(string $id): void
    {
        $group = TagGroup::query()

            ->findOrFail($id);

        $hasTags = Tag::query()
            ->where('tag_group_id', $group->id)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasTags) {
            throw new BusinessException(__('tag.errors.group_not_empty'));
        }

        $group->delete();
    }

    /**
     * 接收删除标签组请求并返回上一页。
     */
    public function asController(Request $request, string $id)
    {
        $ctx = CurrentUserContextData::fromRequest($request);
        $this->handle($id);

        return back();
    }
}
