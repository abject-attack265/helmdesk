<?php

namespace App\Actions\KnowledgeBase;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * 打开创建知识库页面。
 * 创建表单不依赖任何后端首屏数据，无需下发 PageProps。
 */
class ShowCreateKnowledgeBasePageAction
{
    use AsAction;

    /**
     * 返回创建知识库页面。
     */
    public function asController(Request $request): Response
    {

        return Inertia::render('knowledgeBase/Create');
    }
}
