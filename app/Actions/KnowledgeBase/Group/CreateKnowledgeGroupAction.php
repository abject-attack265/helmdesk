<?php

namespace App\Actions\KnowledgeBase\Group;

use App\Data\KnowledgeBase\FormCreateKnowledgeGroupData;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 创建知识库分组的业务 Action：受 2 级限制，名字在同一上级下唯一，
 * sort_order 自动落在同级末尾。
 */
class CreateKnowledgeGroupAction
{
    use AsAction;

    /**
     * 在指定知识库下创建一个分组（顶级或二级），并按同级末尾计算 sort_order。
     */
    public function handle(KnowledgeBase $knowledgeBase, FormCreateKnowledgeGroupData $data): KnowledgeGroup
    {
        $name = trim($data->name);
        $parentId = filled($data->parent_id) ? $data->parent_id : null;

        $this->ensureParentIsTopLevel($knowledgeBase, $parentId);
        $this->ensureNameIsAvailable($knowledgeBase, $name, null, $parentId);

        return KnowledgeGroup::query()->create([
            'knowledge_base_id' => $knowledgeBase->id,
            'parent_id' => $parentId,
            'name' => $name,
            'sort_order' => self::nextSortOrder($knowledgeBase, $parentId),
        ]);
    }

    /**
     * 处理「新建分组」表单提交。
     */
    public function asController(Request $request, string $knowledgeBase): RedirectResponse
    {

        $kb = KnowledgeBase::query()->findOrFail($knowledgeBase);

        $this->handle($kb, FormCreateKnowledgeGroupData::from($request));

        return back();
    }

    /**
     * 校验同一知识库 + 同一上级分组下不存在同名分组。
     */
    public function ensureNameIsAvailable(KnowledgeBase $knowledgeBase, string $name, ?string $exceptId, ?string $parentId): void
    {
        $query = KnowledgeGroup::query()
            ->where('knowledge_base_id', $knowledgeBase->id)
            ->where('name', $name);

        if (filled($parentId)) {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id');
        }

        if (filled($exceptId)) {
            $query->whereKeyNot($exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'name' => __('knowledge_base.groups.name_exists'),
            ]);
        }
    }

    /**
     * 校验上级分组（如有）属于同一知识库且本身就是顶级分组，避免出现 3 级层次。
     */
    public function ensureParentIsTopLevel(KnowledgeBase $knowledgeBase, ?string $parentId): void
    {
        if (! filled($parentId)) {
            return;
        }

        $parent = KnowledgeGroup::query()
            ->where('knowledge_base_id', $knowledgeBase->id)
            ->whereNull('parent_id')
            ->where('is_default', false)
            ->find($parentId);

        if (! $parent) {
            throw ValidationException::withMessages([
                'parent_id' => __('knowledge_base.groups.invalid_parent'),
            ]);
        }
    }

    /**
     * 计算同级末尾的下一个 sort_order，正确处理 parent_id 为空的情况。
     */
    public static function nextSortOrder(KnowledgeBase $knowledgeBase, ?string $parentId): int
    {
        $query = KnowledgeGroup::query()
            ->where('knowledge_base_id', $knowledgeBase->id);

        if (filled($parentId)) {
            $query->where('parent_id', $parentId);
        } else {
            $query->whereNull('parent_id');
        }

        return ((int) $query->max('sort_order')) + 1;
    }
}
