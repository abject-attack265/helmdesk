<?php

namespace App\Data\KnowledgeBase;

use Spatie\LaravelData\Data;

/**
 * 应用级知识库引擎完整配置。
 *
 * cast 到 GeneralSettings.knowledge_engine_config 配置项。
 * 配置项为 null 时按 default()（代码/环境级默认）解析。
 * embedding_model_id 存供应商 model_id（非 AiModel 行 id）；null=未 pin 向量模型；
 * embedding_dimension=null 表示用模型默认维度，由当前应用设置读写。
 *
 * 构造参数全部带默认值，供 model-doc:generate 对模型 cast 做无参实例化探测。
 */
class KnowledgeEngineConfigData extends Data
{
    public function __construct(
        public ?string $embedding_model_id = null,
        public ?int $embedding_dimension = null,
        public bool $vector_index_enabled = false,
        public bool $raptor_index_enabled = false,
        public string $chunking_strategy = 'fixed',
        public int $chunk_max_tokens = 512,
        public int $chunk_overlap_tokens = 64,
    ) {}

    /**
     * 从 config/knowledge.php 读取代码默认配置。
     */
    public static function default(): self
    {
        /** @var array<string, mixed> $defaults */
        $defaults = config('knowledge.defaults', []);

        return new self(
            embedding_model_id: $defaults['embedding_model_id'] ?? null,
            embedding_dimension: $defaults['embedding_dimension'] ?? null,
            vector_index_enabled: (bool) ($defaults['vector_index_enabled'] ?? false),
            raptor_index_enabled: (bool) ($defaults['raptor_index_enabled'] ?? false),
            chunking_strategy: (string) ($defaults['chunking_strategy'] ?? 'fixed'),
            chunk_max_tokens: (int) ($defaults['chunk_max_tokens'] ?? 512),
            chunk_overlap_tokens: (int) ($defaults['chunk_overlap_tokens'] ?? 64),
        );
    }
}
