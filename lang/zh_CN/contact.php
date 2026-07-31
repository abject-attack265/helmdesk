<?php

declare(strict_types=1);

return [
    'types' => [
        'visitor' => '访客',
        'contact' => '联系人',
    ],
    'list_types' => [
        'all' => '全部',
        'contacts' => '联系人',
        'visitors' => '访客',
    ],
    'sources' => [
        'web' => '网页聊天',
        'email' => '邮件',
        'api' => '外部系统',
        'manual' => '手动添加',
        'telegram' => 'Telegram',
        'wechat_official_account' => '微信公众号',
    ],
    'tag_match_modes' => [
        'any' => '任意一个',
        'all' => '全部',
    ],
    'identity_types' => [
        'session' => '访客识别码',
        'email' => '邮箱',
        'phone' => '手机号',
        'external_id' => '外部系统编号',
        'channel_account' => '渠道用户编号',
    ],
    'anonymous_visitor' => '匿名访客',
    'anonymous_visitor_with_suffix' => '匿名访客 #:suffix',
    'identity_already_exists' => '该:type已被联系人「:name」使用',
    'at_least_one_identity' => '请至少填写邮箱或手机号',
    'invalid_phone' => '请输入有效的手机号',
    'invalid_email' => '请输入有效的邮箱地址',
    'invalid_ai_context' => '客户信息摘要格式无效',
    'ai_context_too_large' => '客户信息摘要内容过多，请精简后再保存',
    'merge_open_conversation_conflict' => '这两个联系人在同一渠道都有进行中的会话，请先结束其中一条再合并',
    'identity_manual_management_not_supported' => '该信息由系统或渠道自动管理，不能手动修改或删除',
    'restore_conflict' => '无法恢复，:type「:value」已被联系人「:name」使用',
    'namespace_required_for_external_id' => '渠道账号必须指定所属渠道',
];
