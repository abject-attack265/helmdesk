<?php

namespace App\Actions\KnowledgeBase\Group;

use App\Data\KnowledgeBase\FormUpdateKnowledgeGroupData;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 编辑知识库分组：可重命名，也可改挂到另一个顶级分组下或拉回顶级。
 *
 * 规则：
 * - 不能把分组挂到自己下面。
 * - 受 2 级限制：包含子分组的分组只能保持顶级，不能再被挂到其它分组下。
 * - 新的上级分组必须是同一知识库下的顶级分组。
 * - 名字在新的同级范围内仍需唯一。
 * - 改挂上级时 sort_order 落到目标同级末尾。
 */
class UpdateKnowledgeGroupAction
{
    use AsAction;

    /**
     * 编辑知识库分组：校验约束并更新名称、上级分组及排序。
     */
    public function handle(KnowledgeGroup $group, FormUpdateKnowledgeGroupData $data): void
    {
        if ($group->is_default) {
            throw ValidationException::withMessages([
                'group' => __('knowledge_base.groups.default_locked'),
            ]);
        }

        $name = trim($data->name);
        $newParentId = filled($data->parent_id) ? $data->parent_id : null;

        if ($newParentId !== null && $newParentId === (string) $group->id) {
            throw ValidationException::withMessages([
                'parent_id' => __('knowledge_base.groups.invalid_parent'),
            ]);
        }

        $hasChildren = $group->children()->exists();
        if ($newParentId !== null && $hasChildren) {
            throw ValidationException::withMessages([
                'parent_id' => __('knowledge_base.groups.cannot_move_with_children'),
            ]);
        }

        $createAction = app(CreateKnowledgeGroupAction::class);
        $createAction->ensureParentIsTopLevel($group->knowledgeBase, $newParentId);
        $createAction->ensureNameIsAvailable(
            $group->knowledgeBase,
            $name,
            (string) $group->id,
            $newParentId,
        );

        $updates = ['name' => $name];

        $oldParentId = filled($group->parent_id) ? (string) $group->parent_id : null;
        if ($oldParentId !== $newParentId) {
            $updates['parent_id'] = $newParentId;
            $updates['sort_order'] = CreateKnowledgeGroupAction::nextSortOrder(
                $group->knowledgeBase,
                $newParentId,
            );
        }

        $group->update($updates);
    }

    /**
     * 处理「编辑分组」表单提交。
     */
    public function asController(Request $request, string $knowledgeBase, string $group): RedirectResponse
    {

        $kb = KnowledgeBase::query()->findOrFail($knowledgeBase);
        $groupModel = KnowledgeGroup::query()->where('knowledge_base_id', $kb->id)->findOrFail($group);

        $this->handle($groupModel, FormUpdateKnowledgeGroupData::from($request));

        return back();
    }
}
