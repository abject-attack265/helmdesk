<?php

return [
    'title' => '收件箱',
    'subtitle' => '查看和处理会话',
    'empty_list' => '暂无会话',
    'empty_selection' => '请选择一个会话查看消息',
    'errors' => [
        'invalid_cursor' => '会话列表加载失败，请刷新后重试。',
        'invalid_pane' => '页面加载失败，请刷新后重试。',
    ],
    'views' => [
        'pending' => '排队中',
        'ai' => 'AI 接待中',
        'mine' => '我负责的',
        'teammates' => '同事',
        'closed' => '已关闭',
    ],
    'toolbar' => [
        'channel_filter' => '渠道',
        'channel_any' => '全部渠道',
        'assignee_filter' => '负责人',
        'assignee_any' => '全部负责人',
        'assignee_unassigned' => '未分配',
        'count' => ':count 条会话',
    ],
    'badges' => [
        'pending' => '排队中',
        'ai' => 'AI 接待中',
    ],
    'actions' => [
        'reply' => '发送',
        'send_as_text' => '回复访客',
        'claim' => '开始接待',
        'close' => '关闭会话',
    ],
    'composer' => [
        'placeholder_text' => '输入回复内容，Enter 发送，Shift+Enter 换行',
        'closed_hint' => '会话已关闭，无法继续回复',
    ],
    'selection' => [
        'conversation_boundary' => '第 :index 次会话 · 开始于 :started_at',
        'conversation_ongoing' => '进行中',
        'conversation_closed' => '已关闭',
    ],
];
