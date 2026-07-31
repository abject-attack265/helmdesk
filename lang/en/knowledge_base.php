<?php

return [
    'categories' => [
        'standard' => 'Documents',
        'qa' => 'Q&A',
        'wechat_public' => 'WeChat Official',
        'helper' => [
            'standard' => 'Upload files or enter content directly to help answer customer questions.',
            'qa' => 'Enter common questions and answers to help answer customer questions.',
            'wechat_public' => 'Sync historical articles from a WeChat Official account as knowledge.',
        ],
    ],

    'chunking_strategies' => [
        'fixed' => 'Fixed-size',
        'semantic' => 'Semantic',
    ],

    'engine' => [
        'dimension_value' => ':nd',
        'dimension_default' => 'default',
    ],

    'groups' => [
        'all_documents' => 'All Documents',
        'default_group' => 'Default Group',
        'created' => 'Group created.',
        'updated' => 'Group saved.',
        'deleted' => 'Group deleted.',
        'name_exists' => 'A group with this name already exists in this knowledge base.',
        'has_children' => 'This group still has subgroups. Move or delete them first.',
        'has_documents' => 'This group still has content. Move it to another group first.',
        'default_locked' => 'The default group cannot be edited, moved, or deleted.',
        'invalid_parent' => 'The selected parent group is unavailable. Choose another group.',
        'cannot_move_with_children' => 'This group still has subgroups. Move or delete them first.',
    ],

    'messages' => [
        'created' => 'Knowledge base created.',
        'updated' => 'Knowledge base saved.',
        'deleted' => 'Knowledge base deleted.',
        'in_use' => 'This knowledge base is still linked to a reception plan or experience extraction. Unlink it or delete the related task first.',
        'operation_busy' => 'Another operation is using this knowledge base. Try again shortly.',
        'name_exists' => 'A knowledge base with this name already exists.',
        'invalid_attachment' => 'The knowledge base icon is unavailable. Upload it again.',
        'invalid_embedding_model' => 'Please choose an available Embedding model in the current app.',
        'invalid_embedding_dimension' => 'Please enter the embedding model dimension (an integer between 1 and 65535).',
        'invalid_rerank_model' => 'Please choose an available ReRank model in the current app.',
        'invalid_summary_model' => 'Please choose an available LLM as the deep index summary model.',
        'model_in_use' => 'This model is used by a knowledge base and cannot be disabled or deleted.',
        'provider_in_use' => 'This provider has models used by knowledge bases and cannot be disabled or deleted.',
    ],

    'knowledge_indexing_strategies' => [
        'text' => 'Text index',
        'vector' => 'Standard index',
        'raptor' => 'Deep index',
        'helper' => [
            'text' => 'Canonical text segments; backing store for full-text search and grep. Always enabled.',
            'vector' => 'Builds the baseline index used for everyday knowledge base answers.',
            'raptor' => 'Builds a deeper layered index for long documents and complex questions.',
        ],
    ],

    'documents' => [
        'uploaded' => 'Document uploaded.',
        'uploaded_n' => 'Uploaded :count document(s).',
        'deleted' => 'Document deleted.',
        'statuses' => [
            'pending' => 'Waiting',
            'parsing' => 'Processing',
            'parsed' => 'Processing',
            'indexing' => 'Processing',
            'indexed' => 'Ready to use',
            'failed' => 'Processing failed',
        ],
        'parse_statuses' => [
            'pending' => 'Waiting to read',
            'processing' => 'Reading',
            'succeeded' => 'Read',
            'failed' => 'Could not read',
            'skipped' => 'Skipped',
        ],
        'indexing_statuses' => [
            'idle' => 'Disabled',
            'pending' => 'Waiting',
            'processing' => 'Processing',
            'succeeded' => 'Complete',
            'failed' => 'Processing failed',
        ],
        'stages' => [
            'parse' => 'Read content',
            'vector' => 'Prepare content',
            'raptor' => 'Prepare long documents',
            'full_text' => 'Text matching',
        ],
        'source_types' => [
            'upload' => 'Uploaded',
            'manual' => 'Entered directly',
        ],
        'errors' => [
            'unsupported_extension' => 'Only Word, PDF, TXT, Markdown, and HTML files are supported.',
            'invalid_group' => 'The selected group is unavailable. Choose another group.',
            'default_group_missing' => 'The default group is unavailable. Contact an administrator.',
            'not_manual_editable' => 'Uploaded files cannot be edited here. Upload the updated file instead.',
            'not_document_knowledge_base' => 'Q&A knowledge bases can only contain Q&A entries.',
            'parse_failed' => 'This file could not be read. Try again or use a different file.',
            'embedding_failed' => 'Document processing failed. Try again later or contact an administrator.',
            'summary_failed' => 'Document processing failed. Try again later or contact an administrator.',
            'parsed_content_missing' => 'The document is still being processed. Try again later.',
            'no_segments' => 'No usable content was found. Check the document and try again.',
            'pipeline_busy' => 'The document is being processed. Try deleting it again later.',
            'unsupported_strategy' => 'This processing method is unavailable.',
        ],
    ],

    'qa' => [
        'deleted' => 'Q&A entry deleted.',
        'statuses' => [
            'pending' => 'Waiting',
            'indexing' => 'Processing',
            'indexed' => 'Ready to use',
            'failed' => 'Processing failed',
        ],
        'errors' => [
            'not_qa_knowledge_base' => 'Only Q&A knowledge bases can contain Q&A entries.',
            'question_required' => 'Please enter a question.',
            'answer_required' => 'Please provide at least one answer.',
        ],
    ],
];
