<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 知识库引擎默认配置（代码级）
    |--------------------------------------------------------------------------
    |
    | 知识库引擎配置（向量模型/维度、向量/RAPTOR 开关、分块策略+参数）是应用级的：
    | 应用可以持有一份完整配置；这里是未单独配置时的代码/环境级默认值。
    | embedding_model_id 为 null 表示默认不 pin 向量模型（须由应用显式配置后向量检索才生效）。
    |
    */

    'defaults' => [
        'embedding_model_id' => env('KNOWLEDGE_DEFAULT_EMBEDDING_MODEL_ID'),
        'embedding_dimension' => env('KNOWLEDGE_DEFAULT_EMBEDDING_DIMENSION') !== null
            ? (int) env('KNOWLEDGE_DEFAULT_EMBEDDING_DIMENSION')
            : null,
        'vector_index_enabled' => (bool) env('KNOWLEDGE_DEFAULT_VECTOR_INDEX_ENABLED', false),
        'raptor_index_enabled' => (bool) env('KNOWLEDGE_DEFAULT_RAPTOR_INDEX_ENABLED', false),
        'chunking_strategy' => env('KNOWLEDGE_DEFAULT_CHUNKING_STRATEGY', 'fixed'),
        'chunk_max_tokens' => (int) env('KNOWLEDGE_DEFAULT_CHUNK_MAX_TOKENS', 512),
        'chunk_overlap_tokens' => (int) env('KNOWLEDGE_DEFAULT_CHUNK_OVERLAP_TOKENS', 64),
    ],

];
