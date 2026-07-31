<?php

return [
    'sources' => [
        'vector' => 'Meaning match',
        'fulltext' => 'Text match',
        'raptor' => 'Long-document content',
        'hybrid' => 'Combined search',
    ],
    'fields' => [
        'document' => [
            'parsed_content' => 'Document content',
        ],
        'qa_entry' => [
            'question' => 'Question',
            'similar_question' => 'Other phrasing',
            'answer' => 'Answer',
        ],
    ],
];
