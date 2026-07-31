<?php

return [
    'extraction_statuses' => [
        'running' => '提炼中',
        'completed' => '已完成',
        'failed' => '失败',
    ],
    'candidate_statuses' => [
        'pending' => '待处理',
        'adopted' => '已采纳',
        'discarded' => '已丢弃',
    ],
    'errors' => [
        'extraction_already_running' => '已有一次提炼正在进行中，请等待其完成后再试。',
        'cannot_delete_running' => '进行中的提炼任务不能删除，请等待其完成。',
        'no_background_model' => '没有可用的后台任务模型，请先在 AI 模型管理中配置。',
        'candidate_already_handled' => '该候选经验已被处理过。',
        'invalid_contact_selection' => '所选联系人中包含不存在的，或在所选时间段内没有人工参与过的会话，请刷新后重新选择。',
        'too_many_conversations' => '所选联系人在该时间段内共有 :count 个会话，超过单次提炼上限 :max 个，请缩小时间段或减少勾选。',
        'qa_knowledge_base_not_found' => '目标问答知识库不存在或已被删除。',
    ],
];
