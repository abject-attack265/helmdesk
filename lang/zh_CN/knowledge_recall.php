<?php

return [
    'sources' => [
        'vector' => '按意思查找',
        'fulltext' => '文字匹配',
        'raptor' => '长文档内容',
        'hybrid' => '综合查找',
    ],
    'fields' => [
        'document' => [
            'parsed_content' => '文档内容',
        ],
        'qa_entry' => [
            'question' => '问题',
            'similar_question' => '其他问法',
            'answer' => '答案',
        ],
    ],
];
