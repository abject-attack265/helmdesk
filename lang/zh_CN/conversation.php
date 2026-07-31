<?php

declare(strict_types=1);

return [
    'wechat_unsupported_message' => '暂时只支持文字和图片消息，请换一种方式发送。',
    'statuses' => [
        'open' => '进行中',
        'closed' => '已关闭',
    ],
    'inbox_statuses' => [
        'ai_handling' => 'AI 接待中',
        'teammate_pending' => '待人工接入',
        'teammate_handling' => '人工接待中',
    ],
    'visitor_reply_statuses' => [
        'waiting' => '等待访客回复',
        'not_waiting' => '非等待访客回复',
    ],
    'reply_assistant_modes' => [
        'reply' => '帮我回复',
        'rewrite' => '帮我改写',
    ],
    'reply_polish_tones' => [
        'keep' => '保持原风格',
        'professional' => '专业',
        'friendly' => '友好',
        'concise' => '简洁',
    ],
    'sources' => [
        'manual' => '手动',
        'channel' => '渠道',
    ],
    'entry_modes' => [
        'widget' => '挂件',
        'standalone' => '独立页',
        'telegram' => 'Telegram',
        'wechat_official_account' => '微信公众号',
    ],
    'message_roles' => [
        'visitor' => '访客',
        'ai' => 'AI',
        'teammate' => '同事',
        'tool' => '工具',
    ],
    'message_kinds' => [
        'text' => '文本',
        'image' => '图片',
        'file' => '文件',
        'summary' => '摘要',
        'tool_call' => '工具调用',
        'tool_result' => '工具结果',
    ],
    'message_delivery_statuses' => [
        'sending' => '发送中',
        'sent' => '已发送',
        'failed' => '发送失败',
    ],
    'event_types' => [
        'created' => '已创建',
        'assignment_changed' => '分配已变更',
        'handoff_requested' => '请求人工介入',
        'status_changed' => '状态已变更',
        'reception_turn_started' => '接待轮次开始',
        'reception_tool_called' => '接待工具调用',
        'reception_turn_ended' => '接待轮次结束',
        'feedback_received' => '收到满意度评价',
    ],
    'rating' => [
        'scores' => [
            'positive' => '满意',
            'negative' => '不满意',
        ],
        'handled_by' => [
            'ai' => 'AI 接待',
            'human' => '人工接待',
        ],
        'errors' => [
            'not_closed' => '会话尚未结束，暂不能评价。',
        ],
        'telegram' => [
            'prompt' => '本次服务您还满意吗？',
            'satisfied' => '👍 满意',
            'unsatisfied' => '👎 不满意',
            'thanks' => '感谢您的评价！',
        ],
    ],
    'event_displays' => [
        'feedback_received' => [
            'positive' => '访客给出了好评',
            'negative' => '访客给出了差评',
        ],
        'actors' => [
            'system' => '系统',
        ],
        'created' => [
            'reception' => '访客通过网页端发起了此会话',
            'manual' => ':actor手动创建了此会话',
        ],
        'handoff_requested' => [
            'user_requested' => '访客要求转人工',
            'ai_requested' => 'AI 判断此会话需要人工处理',
            'low_confidence' => 'AI 不确定如何回答，已转人工',
            'tool_failure' => 'AI 处理时遇到异常，已转人工',
            'policy_required' => '按业务规则需人工处理',
            'ai_unavailable' => 'AI 暂时不可用，已转人工',
            'default' => 'AI 请求转人工',
        ],
        'assignment_changed' => [
            'claim' => ':actor开始接待此会话',
            'reply' => ':actor回复并开始接待此会话',
            'transfer_to_human' => ':actor接手了 AI 正在处理的会话',
            'takeover' => ':actor接替了:previous_user处理此会话',
            'transfer_to_teammate' => ':actor将会话转交给了:target',
            'release_to_ai' => ':actor将会话交给了 AI',
            'release_to_queue' => ':actor将会话放回待接待',
        ],
        'status_changed' => [
            'closed' => ':actor关闭了此会话',
            'open' => ':actor重新打开了此会话',
        ],
        'reception_tool_called' => [
            'success' => 'AI 调用了「:tool」工具',
            'failed' => 'AI 调用「:tool」工具出错',
        ],
    ],
    'errors' => [
        'invalid_cursor' => '聊天记录加载失败，请刷新后重试。',
        'invalid_quoted_message' => '引用消息不存在、已撤回或不属于当前会话。',
        'message_not_retryable' => '这条消息当前不能重新发送。',
        'invalid_role_kind_combination' => '消息角色与消息类型组合不合法',
        'empty_message' => '消息内容不能为空。',
        'message_too_long' => '消息太长，请分段发送。',
        'ai_reply_not_allowed' => '会话已转人工，AI 不能再继续回复。',
        'transfer_to_human_required_before_reply' => '请先接手此会话，再回复。',
        'reply_not_allowed_for_assignee' => '这条会话正由其他同事接待，暂时不能直接回复。',
        'reply_translation_stale' => '访客语言已变化，请重新确认翻译内容后再发送。',
        'reply_polish_failed' => '生成回复失败，请稍后重试。',
        'close_not_allowed_for_assignee' => '这条会话正由其他同事接待，暂时不能由你关闭。',
        'already_ai_handling' => '这条会话已经交给 AI 接待。',
        'release_to_ai_not_allowed' => '只能将自己负责的会话交给 AI。',
        'release_to_ai_unavailable' => 'AI 接待当前不可用，无法交给 AI。',
        'already_closed' => '会话已关闭，无法继续操作。',
        'already_open' => '会话已经是进行中状态。',
        'reopen_conflicts_with_open_conversation' => '该客户在当前渠道已有进行中会话，不能恢复这条已关闭会话。',
        'claim_failed' => '接手失败，可能已由其他客服接待。',
        'transfer_to_teammate_not_allowed' => '只能转接自己正在接待的会话。',
        'transfer_target_must_be_teammate' => '请选择其他同事作为转接目标。',
        'transfer_target_not_found' => '请选择当前应用内的同事。',
        'recall_not_assignee' => '只有当前接待人可以撤回消息。',
        'recall_not_owner' => '只能撤回自己发送的消息。',
        'recall_already_recalled' => '消息已被撤回。',
        'recall_kind_not_allowed' => '该类型消息不支持撤回。',
        'recall_window_expired' => '消息已超过 :minutes 分钟，无法撤回。',
        'message_not_found' => '消息不存在或已被删除。',
    ],
    'empty_content' => '无内容',
    'message_recalled_placeholder' => '[消息已撤回]',
];
