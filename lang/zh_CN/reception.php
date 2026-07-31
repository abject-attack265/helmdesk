<?php

return [
    'sandbox' => [
        'no_model' => '当前没有可用的接待模型，无法试运行；请先在 AI 设置中配置可用模型。',
    ],
    'plan_version_statuses' => [
        'published' => '已发布',
        'archived' => '已归档',
    ],
    'plan_version_unusable_reasons' => [
        'archived' => '版本已归档，不能再部署到新渠道（已部署的渠道可继续保留）',
        'reception_model_unavailable' => '接待模型不可用，请先在 AI 设置中恢复',
        'no_usable_version' => '方案暂无可用配置，请先补全可用的接待模型',
    ],
    'persona_tones' => [
        'professional' => '专业',
        'friendly' => '亲切',
        'concise' => '简洁',
    ],
    'human_service_unavailable_reasons' => [
        'outside_business_hours' => '非人工服务时间',
        'no_online_teammate' => '暂无在线客服',
    ],
    'routing_modes' => [
        'ai_first' => 'AI 先接待',
        'teammate_first' => '客服先接待',
    ],
    'defaults' => [
        'handoff_available_notice' => '正在为您转接客服，请稍等。',
        'handoff_no_teammate_notice' => '目前无法转接客服，我会继续为您处理。',
        'outside_hours_notice' => '当前不在人工服务时间，AI 会先接待，客服会在服务时间继续跟进。',
        'ai_unavailable_notice' => '很抱歉，AI 暂时无法回复，正在为您转接客服，请稍候。',
    ],
    'human_service_runtime' => [
        'yes' => '是',
        'no' => '否',
        'heading' => '[人工服务状态]',
        'current_local_time' => '当前本地时间：:time（:timezone）',
        'business_hours' => '人工客服营业时间：:summary',
        'within_business_hours' => '当前是否在营业时间内：:value',
        'has_online_teammate' => '当前是否有可接待人工客服：:value',
        'human_available' => '当前是否允许转人工：:value',
        'answer_scope' => '当访客询问人工客服营业时间、人工是否可用、能否转人工时，只能基于本节状态回答。',
        'call_handoff_tool' => '当访客要求转人工时，必须调用 handoff_to_human；工具会直接把 notice 送达访客。',
        'handoff_terminal' => 'handoff_to_human 会作为本轮面向访客的最终动作。',
        'next_available_at' => '下一次人工营业开始时间：:time',
        'business_hours_not_set' => '未设置固定营业时间。',
        'business_hours_empty' => '未配置可用营业时段。',
        'closed' => '休息',
        'summary_separator' => '；',
        'weekdays' => [
            'monday' => '周一',
            'tuesday' => '周二',
            'wednesday' => '周三',
            'thursday' => '周四',
            'friday' => '周五',
            'saturday' => '周六',
            'sunday' => '周日',
        ],
    ],
    'service_scenarios' => [
        'page_title' => '服务场景',
        'page_description' => '配置接待方案下的服务场景，每个场景对应一类任务处理配方。',
        'empty_title' => '尚未配置任何服务场景',
        'empty_description' => '服务场景描述 AI 接待时可以派发的业务任务，例如订单查询、常见问答、售后处理等。',
        'create_from_scratch' => '从零创建',
        'create_from_template' => '从模板创建',
        'create_from_template_short' => '使用此模板',
        'validation' => [
            'takeover_timeout_exceeds_auto_close' => '转由 AI 接待的等待时间必须短于自动结束时间',
            'business_hours_end_after_start' => '结束时间必须晚于开始时间',
        ],
        'fields' => [
            'name' => '场景名称',
            'description' => '场景简介',
            'description_hint' => '帮助接待 AI 判断什么场景下派发此任务。',
            'instructions' => '场景指令',
            'instructions_hint' => '处理该类型任务时使用的 system prompt，建议写清角色、任务边界与输出要求。',
        ],
        'plan_fields' => [
            'knowledge_bases' => '方案知识库',
            'knowledge_bases_hint' => '任务执行时可检索的知识库（多选，可不选）。',
            'integrations' => '方案集成',
            'integrations_hint' => '任务执行时可调用的集成工具（按集成授权，可进一步收窄工具白名单）。',
        ],
    ],
    'messages' => [
        'plan_name_exists' => '已有同名接待方案，请换一个名称',
        'plan_in_use_channel' => '该方案正在被 :count 个渠道使用，请先为这些渠道选择其他接待方案',
        'plan_in_use_conversation' => '该方案仍有 :count 个进行中的会话，请等待会话结束后再删除',
        'knowledge_base_invalid' => '部分知识库已不可用，请重新选择',
        'integration_invalid' => '部分集成已不可用，请重新选择',
        'service_scenario_template_not_found' => '指定的服务场景模板不存在',
        'service_scenario_name_duplicated' => '同一方案内的服务场景名称不能重复（忽略大小写与首尾空格）',
    ],
    'telegram' => [
        'start_message' => '您好，请直接发送您的问题，我们会尽快为您处理。',
    ],
    'errors' => [
        'message_empty' => '消息内容不能为空',
        'message_too_long' => '消息太长，请分段发送',
    ],
];
