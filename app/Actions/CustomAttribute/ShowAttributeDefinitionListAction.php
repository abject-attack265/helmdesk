<?php

namespace App\Actions\CustomAttribute;

use App\Data\CustomAttribute\ListAttributeDefinitionItemData;
use App\Data\CustomAttribute\ShowListAttributeDefinitionPagePropsData;
use App\Data\EnumOptionData;
use App\Enums\AttributeType;
use App\Models\AttributeDefinition;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 展示自定义属性定义列表。
 */
class ShowAttributeDefinitionListAction
{
    use AsAction;

    public function handle(): ShowListAttributeDefinitionPagePropsData
    {
        $definitions = AttributeDefinition::query()
            ->withCount('contactAttributeValues')
            ->ordered()
            ->get();

        return new ShowListAttributeDefinitionPagePropsData(
            definition_list: $definitions
                ->map(fn (AttributeDefinition $definition) => ListAttributeDefinitionItemData::fromModel($definition))
                ->all(),
            type_options: EnumOptionData::fromCases(AttributeType::cases()),
        );
    }

    public function asController(Request $request): Response
    {
        $props = $this->handle();

        return Inertia::render('appSettings/datas/Attribute', $props->toArray());
    }
}
