<?php

declare(strict_types=1);

namespace App\Services\AiRuntime;

use NeuronAI\HttpClient\HttpClientInterface;

/**
 * MiniMax 接待 / 后台任务模型 provider。
 *
 * MiniMax-M3 走 OpenAI 兼容端点，支持 video_url 视频输入。
 *
 * 额外固定 reasoning_split=true：MiniMax-M3 默认把思考内联进 content，
 * 开启后思考改走独立的 reasoning_content 字段，正文保持干净（NeuronAI 只取 content），无需下游剥离。
 * reasoning_split 只控制返回格式、不开关思考，推理质量不受影响。
 */
class MiniMaxProvider extends MultimodalReasoningProvider
{
    /**
     * 注入 base_uri / key / model，并固定 reasoning_split=true 让思考与正文分流。
     */
    public function __construct(string $baseUri, string $key, string $model, ?HttpClientInterface $httpClient = null)
    {
        parent::__construct($baseUri, $key, $model, ['reasoning_split' => true], httpClient: $httpClient);
    }
}
