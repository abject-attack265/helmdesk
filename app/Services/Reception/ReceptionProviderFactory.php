<?php

namespace App\Services\Reception;

use App\Data\AiRuntime\RuntimeModelCandidateData;
use App\Enums\AiProviderProtocol;
use App\Models\AiModel;
use App\Services\AiRuntime\AiHttpClientFactory;
use App\Services\AiRuntime\MiniMaxProvider;
use App\Services\AiRuntime\MultimodalReasoningProvider;
use App\Services\AiRuntime\OpenAICompatibleProvider;
use InvalidArgumentException;
use NeuronAI\HttpClient\HttpClientInterface;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Anthropic\Anthropic;
use NeuronAI\Providers\Gemini\Gemini;
use NeuronAI\Providers\OpenAI\OpenAI;

/**
 * 把运行时模型候选映射成 NeuronAI 的 AIProviderInterface 实例。
 *
 * 底层只有三种原生协议（openai / anthropic / gemini）；其它兼容品牌（deepseek/qwen/ollama 等）
 * 在品牌目录里映射成 openai 协议并预设 base_uri。带 base_uri 的 openai 使用
 * OpenAICompatibleProvider，以 json_object 处理兼容端点的结构化输出。
 * MiniMax / 小米 MiMo 等国产多模态推理模型使用 MultimodalReasoningProvider，
 * 在 OpenAI 兼容之上支持 video_url 视频输入。
 *
 * 凭据字段沿用既有约定：`key` = API Key，`base_uri` = 可选的 base URL 覆盖。
 */
class ReceptionProviderFactory
{
    /**
     * 注入 AI HTTP Client 工厂。
     */
    public function __construct(
        private readonly AiHttpClientFactory $httpClients,
    ) {}

    /**
     * 按模型候选构造对应的 NeuronAI provider。
     */
    public function make(RuntimeModelCandidateData $candidate): AIProviderInterface
    {
        $key = $candidate->credentials['key'] ?? '';
        $baseUri = trim($candidate->credentials['base_uri'] ?? '');

        if ($candidate->model_id === '') {
            throw new InvalidArgumentException('接待模型候选缺少 model_id。');
        }

        $httpClient = $this->httpClients->make();

        return match ($candidate->protocol) {
            AiProviderProtocol::OpenAI => $this->makeOpenAiLike($candidate, $baseUri, $key, $httpClient),
            AiProviderProtocol::Anthropic => new Anthropic($key, $candidate->model_id, httpClient: $httpClient),
            AiProviderProtocol::Gemini => new Gemini($key, $candidate->model_id, httpClient: $httpClient),
        };
    }

    /**
     * 构造 OpenAI 协议族的 provider：MiniMax / 小米 MiMo 走 MultimodalReasoningProvider
     * 以支持 video_url 视频输入；其余兼容品牌走通用兼容 provider，官方 OpenAI 走原生。
     */
    private function makeOpenAiLike(
        RuntimeModelCandidateData $candidate,
        string $baseUri,
        string $key,
        HttpClientInterface $httpClient,
    ): AIProviderInterface {
        if ($baseUri === '') {
            return new OpenAI($key, $candidate->model_id, httpClient: $httpClient);
        }

        return match ($candidate->brand) {
            'minimax' => new MiniMaxProvider($baseUri, $key, $candidate->model_id, httpClient: $httpClient),
            'xiaomi', 'xiaomi-token-plan' => new MultimodalReasoningProvider($baseUri, $key, $candidate->model_id, httpClient: $httpClient),
            default => new OpenAICompatibleProvider($baseUri, $key, $candidate->model_id, httpClient: $httpClient),
        };
    }

    /**
     * 按 AiModel 及其供应商配置构造 NeuronAI provider。
     */
    public function makeForModel(AiModel $model): AIProviderInterface
    {
        return $this->make(RuntimeModelCandidateData::fromModel($model));
    }
}
