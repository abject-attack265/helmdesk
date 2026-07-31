<?php

return [
    'categories' => [
        'standard' => '文档知识库',
        'qa' => '问答知识库',
        'wechat_public' => '公众号知识库',
        'helper' => [
            'standard' => '上传文件或直接填写内容，供接待客户时查找答案。',
            'qa' => '填写常见问题和答案，供接待客户时查找答案。',
            'wechat_public' => '同步公众号历史文章作为知识来源。',
        ],
    ],

    'chunking_strategies' => [
        'fixed' => '普通分段',
        'semantic' => '语义分段',
    ],

    'engine' => [
        'dimension_value' => ':n 维',
        'dimension_default' => '默认维',
    ],

    'groups' => [
        'all_documents' => '全部文档',
        'default_group' => '默认分组',
        'created' => '分组已创建。',
        'updated' => '分组已保存。',
        'deleted' => '分组已删除。',
        'name_exists' => '这个知识库中已有同名分组。',
        'has_children' => '这个分组中还有子分组，请先移走或删除它们。',
        'has_documents' => '这个分组中还有内容，请先移到其他分组。',
        'default_locked' => '默认分组不能编辑、移动或删除。',
        'invalid_parent' => '所选上级分组不可用，请重新选择。',
        'cannot_move_with_children' => '这个分组中还有子分组，请先移走或删除它们。',
    ],

    'messages' => [
        'created' => '知识库已创建。',
        'updated' => '知识库已保存。',
        'deleted' => '知识库已删除。',
        'in_use' => '这个知识库仍关联接待方案或经验提炼任务，请先解除关联或删除相关任务。',
        'operation_busy' => '知识库正在处理中，请稍后再试。',
        'name_exists' => '同名知识库已存在。',
        'invalid_attachment' => '知识库图标不可用，请重新上传。',
        'invalid_embedding_model' => '请选择当前应用中可用的嵌入模型。',
        'invalid_embedding_dimension' => '请填写嵌入模型的向量维度（1-65535 的整数）。',
        'invalid_rerank_model' => '请选择当前应用中可用的重排序模型。',
        'invalid_summary_model' => '请选择当前应用中可用的大语言模型作为深度索引摘要模型。',
        'model_in_use' => '该模型已被知识库使用，不能停用或删除。',
        'provider_in_use' => '该供应商已有模型被知识库使用，不能停用或删除。',
    ],

    'knowledge_indexing_strategies' => [
        'text' => '文本索引',
        'vector' => '标准索引',
        'raptor' => '深度索引',
        'helper' => [
            'text' => '解析后的文本分段，是全文检索与 grep 的存储载体，始终启用。',
            'vector' => '为文档建立基础索引，用于日常知识库问答。',
            'raptor' => '为长文档建立更深入的层级索引，提升复杂问题的命中效果。',
        ],
    ],

    'documents' => [
        'uploaded' => '文档已上传。',
        'uploaded_n' => '已上传 :count 个文档。',
        'deleted' => '文档已删除。',
        'statuses' => [
            'pending' => '等待处理',
            'parsing' => '处理中',
            'parsed' => '处理中',
            'indexing' => '处理中',
            'indexed' => '可使用',
            'failed' => '处理失败',
        ],
        'parse_statuses' => [
            'pending' => '等待读取',
            'processing' => '正在读取',
            'succeeded' => '已读取',
            'failed' => '读取失败',
            'skipped' => '已跳过',
        ],
        'indexing_statuses' => [
            'idle' => '未启用',
            'pending' => '等待处理',
            'processing' => '正在处理',
            'succeeded' => '已完成',
            'failed' => '处理失败',
        ],
        'stages' => [
            'parse' => '读取内容',
            'vector' => '整理内容',
            'raptor' => '整理长文档',
            'full_text' => '文字匹配',
        ],
        'source_types' => [
            'upload' => '上传',
            'manual' => '直接填写',
        ],
        'errors' => [
            'unsupported_extension' => '只支持 Word、PDF、TXT、Markdown 和 HTML 文件。',
            'invalid_group' => '所选分组不可用，请重新选择。',
            'default_group_missing' => '默认分组不可用，请联系管理员。',
            'not_manual_editable' => '上传的文件不能在线编辑，如需修改请重新上传。',
            'not_document_knowledge_base' => '问答知识库只能添加问答。',
            'parse_failed' => '无法读取这个文件，请重试或换一个文件。',
            'embedding_failed' => '文档处理失败，请稍后重试；如仍失败，请联系管理员。',
            'summary_failed' => '文档处理失败，请稍后重试；如仍失败，请联系管理员。',
            'parsed_content_missing' => '文档还在处理中，请稍后重试。',
            'no_segments' => '没有读取到可用内容，请检查文档后重试。',
            'pipeline_busy' => '文档正在处理中，请稍后再删除。',
            'unsupported_strategy' => '当前处理方式不可用。',
        ],
    ],

    'qa' => [
        'deleted' => '问答已删除。',
        'statuses' => [
            'pending' => '等待处理',
            'indexing' => '处理中',
            'indexed' => '可使用',
            'failed' => '处理失败',
        ],
        'errors' => [
            'not_qa_knowledge_base' => '只有问答知识库才能添加问答。',
            'question_required' => '请填写问题。',
            'answer_required' => '请至少填写一个答案。',
        ],
    ],
];
