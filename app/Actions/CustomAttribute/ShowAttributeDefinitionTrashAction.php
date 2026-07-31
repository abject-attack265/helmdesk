<?php

namespace App\Actions\CustomAttribute;

use App\Data\CustomAttribute\ListAttributeDefinitionItemData;
use App\Data\CustomAttribute\ShowAttributeDefinitionTrashPagePropsData;
use App\Data\SimplePaginationData;
use App\Models\AttributeDefinition;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 查询自定义属性回收站。
 */
class ShowAttributeDefinitionTrashAction
{
    use AsAction;

    public function handle(
        int $page = 1,
        int $perPage = SimplePaginationData::DEFAULT_PER_PAGE,
    ): ShowAttributeDefinitionTrashPagePropsData {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 50));

        $paginator = AttributeDefinition::query()

            ->onlyTrashed()
            ->withCount('contactAttributeValues')
            ->orderByDesc('deleted_at')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return new ShowAttributeDefinitionTrashPagePropsData(
            trashed_definition_list: $paginator->getCollection()
                ->map(fn (AttributeDefinition $definition) => ListAttributeDefinitionItemData::fromModel($definition))
                ->all(),
            trashed_definition_list_pagination: SimplePaginationData::fromPaginator($paginator),
        );
    }

    public function asController(Request $request): Response
    {

        return Inertia::render('appSettings/datas/AttributeTrash', $this->handle(
            page: (int) $request->query('page', 1),
            perPage: (int) $request->query('per_page', SimplePaginationData::DEFAULT_PER_PAGE),
        )->toArray());
    }
}
