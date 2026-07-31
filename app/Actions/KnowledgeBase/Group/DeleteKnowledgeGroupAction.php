<?php

namespace App\Actions\KnowledgeBase\Group;

use App\Exceptions\BusinessException;
use App\Models\KnowledgeBase;
use App\Models\KnowledgeGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 删除没有子分组、文档或问答的知识库分组。
 */
class DeleteKnowledgeGroupAction
{
    use AsAction;

    /**
     * 删除空分组，默认分组或仍有内容时拒绝。
     */
    public function handle(KnowledgeGroup $group): void
    {
        if ($group->is_default) {
            throw new BusinessException(__('knowledge_base.groups.default_locked'));
        }

        if ($group->children()->exists()) {
            throw new BusinessException(__('knowledge_base.groups.has_children'));
        }

        if ($group->documents()->exists()) {
            throw new BusinessException(__('knowledge_base.groups.has_documents'));
        }

        if ($group->qaEntries()->exists()) {
            throw new BusinessException(__('knowledge_base.groups.has_documents'));
        }

        $group->delete();
    }

    /**
     * 处理「删除分组」请求并返回上一页。
     */
    public function asController(Request $request, string $knowledgeBase, string $group): RedirectResponse
    {

        $kb = KnowledgeBase::query()->findOrFail($knowledgeBase);
        $groupModel = KnowledgeGroup::query()->where('knowledge_base_id', $kb->id)->findOrFail($group);

        $this->handle($groupModel);

        return back();
    }
}
