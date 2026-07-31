<?php

declare(strict_types=1);

return [
    'sources' => [
        'manual' => '手动创建',
        'system' => '系统创建',
        'ai' => 'AI 创建',
        'import' => '导入创建',
        'channel' => '渠道参数',
    ],
    'scopes' => [
        'conversation' => '会话',
        'contact' => '联系人',
    ],
    'default_groups' => [
        'channel' => '渠道参数',
    ],
    'errors' => [
        'name_exists' => '这个标签组里已有同名标签',
        'locked_cannot_delete' => '这个标签已锁定，不能删除',
        'locked_cannot_be_merged' => '这个标签已锁定，不能合并到其他标签',
        'merge_same_tag' => '请选择两个不同的标签',
        'restore_name_conflict' => '原标签组中已有同名标签，请先重命名现有标签',
        'group_name_exists' => '已有同名标签组',
        'group_scope_mismatch' => '标签用途不一致，请选择用于同一对象的标签',
        'group_not_empty' => '请先移动或删除组内标签，再删除标签组',
        'group_required' => '请选择标签组',
    ],
    'merge_success' => '标签合并成功',
    'restore_success' => '标签恢复成功',
];
