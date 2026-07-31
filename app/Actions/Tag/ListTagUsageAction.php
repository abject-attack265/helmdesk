<?php

namespace App\Actions\Tag;

use App\Data\CurrentUserContextData;
use App\Data\Tag\TagUsageData;
use App\Models\Tag;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 统计标签在联系人中的使用情况。
 */
class ListTagUsageAction
{
    use AsAction;

    public function handle(string $id): TagUsageData
    {
        $tag = Tag::query()

            ->withTrashed()
            ->withCount('contacts')
            ->findOrFail($id);

        $contactUsageCount = (int) ($tag->contacts_count ?? 0);

        return new TagUsageData(
            contact_usage_count: $contactUsageCount,
            usage_count: $contactUsageCount,
        );
    }

    public function asController(Request $request, string $id)
    {
        $ctx = CurrentUserContextData::fromRequest($request);

        return $this->handle($id)->toArray();
    }
}
