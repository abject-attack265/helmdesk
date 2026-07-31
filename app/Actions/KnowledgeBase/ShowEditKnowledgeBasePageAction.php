<?php

namespace App\Actions\KnowledgeBase;

use App\Data\KnowledgeBase\KnowledgeBaseData;
use App\Data\KnowledgeBase\ShowEditKnowledgeBasePagePropsData;
use App\Models\KnowledgeBase;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 打开编辑知识库页面，并带上当前基础信息。
 */
class ShowEditKnowledgeBasePageAction
{
    use AsAction;

    /**
     * 组装编辑知识库页面所需的表单数据。
     */
    public function handle(string $knowledgeBaseId): ShowEditKnowledgeBasePagePropsData
    {
        $knowledgeBase = KnowledgeBase::query()
            ->with(['avatar.storageProfile'])

            ->findOrFail($knowledgeBaseId);

        return new ShowEditKnowledgeBasePagePropsData(
            knowledge_base_form: KnowledgeBaseData::fromModel($knowledgeBase),
        );
    }

    /**
     * 返回编辑知识库页面。
     */
    public function asController(Request $request, string $knowledgeBase): Response
    {

        return Inertia::render('knowledgeBase/Edit', $this->handle($knowledgeBase)->toArray());
    }
}
