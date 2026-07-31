<?php

namespace App\Services\KnowledgeBase;

use App\Enums\AiProviderProtocol;
use App\Exceptions\BusinessException;
use App\Models\AiModel;
use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use NeuronAI\RAG\Embeddings\GeminiEmbeddingsProvider;
use NeuronAI\RAG\Embeddings\OpenAIEmbeddingsProvider;
use NeuronAI\RAG\Embeddings\OpenAILikeEmbeddings;

/**
 * 把知识库 embedding 模型映射成 NeuronAI 的 EmbeddingsProviderInterface。
 *
 * 协议映射与 ReceptionProviderFactory 一致：openai（含带 base_uri 的兼容品牌走 OpenAILike）、gemini。
 * Anthropic 协议没有 embedding 能力，作为 embedding 模型时直接报错。
 *
 * dimensions 来自知识库引擎 pin 的向量规格（KnowledgeEngine::embeddingDimension）：
 * 非空时透传给 OpenAI 的 dimensions 参数取定长向量；null 时让模型返回其默认维度。
 */
class KnowledgeEmbeddingProviderFactory
{
    /**
     * 按 embedding 模型构造对应的 NeuronAI embeddings provider。
     */
    public function make(AiModel $model, ?int $dimensions = null): EmbeddingsProviderInterface
    {
        $provider = $model->provider;
        if ($provider === null) {
            throw new BusinessException(__('knowledge_base.messages.invalid_embedding_model'));
        }

        $protocol = $provider->protocol instanceof AiProviderProtocol
            ? $provider->protocol
            : AiProviderProtocol::from((string) $provider->protocol);

        $credentials = is_array($provider->credentials) ? $provider->credentials : [];
        $key = (string) ($credentials['key'] ?? '');
        $baseUri = trim((string) ($credentials['base_uri'] ?? ''));
        $modelId = (string) $model->model_id;

        return match ($protocol) {
            AiProviderProtocol::OpenAI => $baseUri !== ''
                ? new OpenAILikeEmbeddings($baseUri, $key, $modelId, $dimensions)
                : new OpenAIEmbeddingsProvider($key, $modelId, $dimensions),
            AiProviderProtocol::Gemini => new GeminiEmbeddingsProvider($key, $modelId),
            AiProviderProtocol::Anthropic => throw new BusinessException(__('knowledge_base.messages.invalid_embedding_model')),
        };
    }
}
