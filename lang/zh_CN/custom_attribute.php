<?php

declare(strict_types=1);

return [
    'types' => [
        'text' => '单行文字',
        'textarea' => '多行文字',
        'number' => '数字',
        'date' => '日期',
        'boolean' => '是 / 否',
        'single_select' => '单选',
        'multi_select' => '多选',
    ],
    'sources' => [
        'manual' => '手动',
        'api' => 'API',
        'import' => '导入',
        'workflow' => '自动化',
        'ai' => 'AI',
        'merge' => '合并',
        'channel' => '渠道参数',
    ],
    'reserved_key' => '内部标识「:key」由系统保留，请换一个',
    'duplicate_key' => '内部标识「:key」已被使用；如字段在回收站中，请先恢复或换一个标识',
    'invalid_key_format' => '内部标识请以小写字母开头，只使用小写字母、数字和下划线',
    'invalid_attribute_type' => '请选择正确的填写方式',
    'invalid_option_config' => '请至少添加一个完整的选项',
    'unsupported_filterable_type' => '只有单选、是 / 否、日期和数字可以用于联系人筛选',
    'invalid_attribute_filter' => '自定义字段的筛选条件有误',
    'attribute_archived' => '这个字段已在回收站，恢复后才能修改',
    'invalid_attribute_value' => '「:name」的填写内容不符合要求',
    'option_code_in_use' => '选项「:code」已有联系人使用，不能修改或删除',
    'option_code_duplicate' => '选项标识不能重复',
    'invalid_reorder_payload' => '字段排序保存失败，请刷新后重试',
];
