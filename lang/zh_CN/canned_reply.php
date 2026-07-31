<?php

declare(strict_types=1);

return [
    'visibilities' => [
        'app' => '团队共享',
        'personal' => '仅自己使用',
    ],
    'token_kinds' => [
        'contact' => '联系人',
        'conversation' => '会话',
        'teammate' => '客服',
        'app' => '应用',
    ],
    'tokens' => [
        'contact_name' => '联系人姓名',
        'contact_email' => '联系人邮箱',
        'contact_primary_phone' => '联系人手机号',
        'conversation_id' => '会话 ID',
        'conversation_subject' => '会话主题',
        'teammate_name' => '当前客服姓名',
        'app_name' => '应用名称',
    ],
    'warnings' => [
        'missing_value' => '变量 :token 在当前会话中没有值，已保留原文',
    ],
    'errors' => [
        'forbidden' => '你没有权限使用或管理这条快捷回复',
        'shortcut_exists' => '当前使用范围内已存在相同的快捷指令',
    ],
];
