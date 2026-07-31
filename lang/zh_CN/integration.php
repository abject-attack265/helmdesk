<?php

return [
    'page' => [
        'title' => '集成',
        'description' => '连接外部系统，让 AI 和客服使用其中的工具和数据。',
        'empty' => '还没有添加集成。',
        'empty_action' => '添加第一个集成',
    ],
    'providers' => [
        'mcp' => 'MCP',
        'business_system' => '业务系统',
        'mock_business_system' => '业务系统（mock）',
    ],
    'transports' => [
        'streamable_http' => 'Streamable HTTP',
        'http' => 'HTTP',
    ],
    'auth_presets' => [
        'none' => '无需验证',
        'bearer' => '访问令牌',
        'header' => '自定义请求头',
    ],
    'sync_statuses' => [
        'pending' => '尚未更新',
        'syncing' => '更新中',
        'success' => '已更新',
        'failed' => '更新失败',
    ],
    'fields' => [
        'name' => '集成名称',
        'transport' => '传输协议',
        'endpoint_url' => '服务地址',
        'auth_preset' => '验证方式',
        'bearer_token' => '访问令牌',
        'auth_header_name' => '请求头名称',
        'auth_header_value' => '请求头内容',
        'custom_headers' => '自定义请求头',
        'timeout_seconds' => '最长等待时间（秒）',
        'tools_count' => '工具',
        'last_synced_at' => '最后更新时间',
    ],
    'actions' => [
        'add' => '添加集成',
        'save' => '保存',
        'test_connection' => '测试连接',
        'sync_tools' => '更新工具',
        'delete' => '删除',
    ],
    'placeholders' => [
        'keep_credential' => '保留原值（不修改）',
    ],
    'messages' => [
        'created' => '集成已创建。',
        'check_succeeded' => '连接正常。',
        'sync_succeeded' => '已更新 :total 个工具（新增 :added 个，下线 :removed 个）。',
        'sync_all_queued' => '已开始更新 :count 个集成的工具。',
        'tool_disabled_due_to_removal' => '这个工具已下线，无法启用。',
    ],
    'tool' => [
        'removed_badge' => '已下线',
        'description_empty' => '暂无说明。',
        'schema_label' => 'Input Schema',
        'annotations_label' => '工具标注',
    ],
    'delete' => [
        'title' => '删除集成 ":name"?',
        'description' => '删除后，其中的 :count 个工具也会一并移除。',
    ],
    'runtime' => [
        'check' => [
            'succeeded' => '连接正常。',
            'failed' => '连接失败：:error',
            'timeout' => '连接超时，请稍后重试。',
            'unauthorized' => '验证失败，请检查访问令牌或验证信息。',
            'protocol_error' => '无法连接 MCP 服务：:error',
        ],
        'validate' => [
            'succeeded' => '连接信息可用。',
            'missing_endpoint' => '请填写服务地址。',
            'unsupported_transport' => '暂不支持这种连接方式。',
            'unsafe_endpoint' => '这个服务地址无法使用，请填写可公开访问的地址。',
        ],
        'list_tools' => [
            'succeeded' => '工具列表已更新。',
            'failed' => '更新工具失败：:error',
            'invalid_response' => '业务系统返回的工具列表格式有误。',
        ],
        'bridge' => [
            'not_configured' => 'MCP 服务暂不可用，请稍后重试。',
            'unavailable' => 'MCP 服务暂不可用，请稍后重试。',
            'invalid_response' => 'MCP 服务返回的数据有误，请检查服务配置。',
            'request_failed' => '无法连接 MCP 服务，请检查地址后重试。',
        ],
        'request' => [
            'invalid_payload' => '提交的信息有误：:error',
        ],
    ],
    'mock' => [
        'tools' => [
            'query_order' => [
                'description' => '按订单号或邮箱查询订单状态、物流与金额',
                'order_no' => '订单号',
                'email' => '下单邮箱',
            ],
            'query_customer' => [
                'description' => '按邮箱查询客户等级与累计订单数',
                'email' => '客户邮箱',
            ],
            'unknown' => '未知工具「:name」，无可用数据。',
        ],
        'panel' => [
            'customer_overview' => '客户概况',
            'customer_tier' => '客户等级',
            'lifetime_orders' => '累计订单',
            'member_since' => '注册时间',
            'member_profile' => '会员主页',
            'recent_orders' => '最近订单',
            'noise_cancelling_headphones' => '无线降噪耳机 ×1',
            'mechanical_keyboard' => '机械键盘 ×1',
            'usb_c_cable' => 'USB-C 数据线 ×2',
            'amount_299' => '￥299.00',
            'amount_459' => '￥459.00',
            'amount_58' => '￥58.00',
            'shipped' => '已发货',
            'completed' => '已完成',
            'refunded' => '已退款',
        ],
        'order' => [
            'status' => '已发货',
            'amount' => '￥299.00',
            'content' => '订单 :order_no：状态「:status」，物流单号 :tracking_no，金额 :amount，下单时间 :placed_at。',
        ],
        'customer' => [
            'name' => '张三',
            'level' => '金牌会员',
            'content' => '客户 :name（:email）：等级「:level」，累计 :order_count 笔订单。',
        ],
    ],
];
