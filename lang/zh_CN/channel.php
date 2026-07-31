<?php

return [
    'types' => [
        'web' => '网站',
        'telegram' => 'Telegram',
        'wechat_official_account' => '微信公众号',
    ],
    'reception_languages' => [
        'zh-CN' => '简体中文',
        'en' => '英语',
    ],
    'statuses' => [
        'active' => '已启用',
        'disabled' => '未启用',
    ],
    'web_visitor_identity_modes' => [
        'actual_receptionist' => '显示实际接待人员',
        'unified_service' => '统一显示为客服',
    ],
    'web_widget_entry_modes' => [
        'bubble' => '默认聊天按钮',
        'custom' => '使用网站自己的按钮',
    ],
    'web_widget_entry_positions' => [
        'right' => '页面右侧',
        'left' => '页面左侧',
    ],
    'web_widget_entry_styles' => [
        'system' => '默认样式',
        'custom' => '自定义样式',
    ],
    'web_widget_icon_sizes' => [
        'small' => '小（36 × 36 像素）',
        'medium' => '中（48 × 48 像素）',
        'large' => '大（52 × 52 像素）',
    ],
    'defaults' => [
        'assistant_name' => 'AI 助手',
    ],
    'messages' => [
        'created' => '渠道已添加。',
        'basic_saved' => '基本信息已保存。',
        'widget_saved' => '网站嵌入设置已保存。',
        'standalone_saved' => '聊天链接设置已保存。',
        'deleted' => '渠道已删除。',
        'restored' => '渠道已恢复。',
        'status_updated' => '渠道状态已更新。',
        'active_reception_plan_required' => '请先为渠道选择接待方案。',
        'active_reception_plan_invalid' => '当前接待方案不可用，请选择其他方案或先完成方案设置。',
        'invalid_reception_plan_version' => '请选择当前应用内的接待方案。',
        'invalid_reception_plan' => '请选择当前应用内的接待方案。',
        'reception_plan_no_usable_version' => '所选接待方案目前不可用，请先完成方案设置。',
        'reception_plan_version_archived' => '所选接待方案已归档，请选择其他方案。',
        'reception_plan_version_model_unavailable' => '所选接待方案目前不可用，请先检查方案设置。',
        'invalid_attachment' => '图片不可用，请重新上传。',
        'entry_icon_pair_required' => '关闭时图标和展开时图标需一起上传；都不上传则使用默认图标。',
    ],
    'query_params' => [
        'locale' => '向运行时传入访客语言',
        'name' => '预填访客姓名',
        'email' => '预填访客邮箱',
        'external_id' => '传入访客外部 ID',
        'ref' => '传入来源引用标识',
        'utm_source' => '传入 UTM 来源',
        'utm_medium' => '传入 UTM 渠道媒介',
        'utm_campaign' => '传入 UTM 活动名称',
    ],
    'query_param_labels' => [
        'locale' => 'locale',
        'name' => 'name',
        'email' => 'email',
        'external_id' => 'external_id',
        'ref' => 'ref',
        'utm_source' => 'utm_source',
        'utm_medium' => 'utm_medium',
        'utm_campaign' => 'utm_campaign',
    ],
    'web' => [
        'param_targets' => [
            'contact_name' => '联系人姓名',
            'contact_email' => '联系人邮箱',
            'contact_phone' => '联系人手机号',
            'contact_external_id' => '联系人外部 ID',
            'contact_importance' => '重点客户标记',
            'attribute' => '自定义字段',
            'tag' => '联系人标签',
        ],
        'param_trust' => [
            'signed_only' => '仅已验证的访客可填写',
            'always' => '所有访客均可填写',
        ],
        'param_write_modes' => [
            'only_if_empty' => '仅在没有内容时填写',
            'overwrite' => '每次都更新',
        ],
    ],
    'telegram' => [
        'webhook_registered' => 'Telegram 机器人已连接。',
        'webhook_modes' => [
            'direct' => 'HelmDesk 直接连接',
            'gateway' => '由外部系统转发',
        ],
        'errors' => [
            'bot_token_required' => '请填写机器人 Token。',
            'bot_already_connected' => '这个机器人已连接到其他渠道。请继续使用原渠道；如果原渠道已删除，请先从回收站恢复。',
            'connection_busy' => '这个渠道正在连接 Telegram，请稍后再试。',
            'invalid_bot_token' => 'Telegram 无法验证这个机器人 Token，请检查后重试。',
            'webhook_registration_failed' => '暂时无法连接 Telegram，请稍后重试。',
            'webhook_gateway_managed' => '当前由外部系统转发消息，无法在这里连接 Telegram。',
        ],
    ],
];
