<?php

namespace App\Data\KnowledgeBase;

use Spatie\LaravelData\Data;

/**
 * 编辑知识库页面 props。
 * 由 ShowEditKnowledgeBasePageAction 返回给 resources/js/pages/knowledgeBase/Edit.vue，用于渲染表单默认值。
 */
class ShowEditKnowledgeBasePagePropsData extends Data
{
    public function __construct(
        public KnowledgeBaseData $knowledge_base_form,
    ) {}
}
