<?php

namespace App\Actions\CustomAttribute;

use App\Models\AttributeDefinition;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * 归档自定义属性定义。
 */
class ArchiveAttributeDefinitionAction
{
    use AsAction;

    public function handle(string $definitionId): AttributeDefinition
    {
        $definition = AttributeDefinition::query()->findOrFail($definitionId);

        $definition->delete();

        return $definition;
    }

    public function asController(Request $request, string $id): Response
    {

        $this->handle($id);

        return back();
    }
}
