<?php

namespace App\Actions\CustomAttribute;

use App\Models\AttributeDefinition;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * 恢复已归档的自定义属性定义。
 */
class RestoreAttributeDefinitionAction
{
    use AsAction;

    public function handle(string $definitionId): AttributeDefinition
    {
        $definition = AttributeDefinition::query()

            ->onlyTrashed()
            ->findOrFail($definitionId);

        $maxOrder = AttributeDefinition::query()
            ->active()
            ->max('display_order') ?? -1;

        $definition->display_order = $maxOrder + 1;
        $definition->restore();
        $definition->save();

        return $definition;
    }

    public function asController(Request $request, string $id): Response
    {

        $this->handle($id);

        return back();
    }
}
