<?php

use App\Services\AiRuntime\MiniMaxProvider;
use App\Services\AiRuntime\MultimodalReasoningProvider;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\HttpClient\HttpClientInterface;
use NeuronAI\HttpClient\HttpRequest;
use NeuronAI\HttpClient\HttpResponse;
use NeuronAI\HttpClient\StreamInterface;

/**
 * 捕获最后一次请求体并返回固定响应的假 HTTP 客户端。
 */
function fakeReasoningHttpClient(): HttpClientInterface
{
    return new class implements HttpClientInterface
    {
        public ?HttpRequest $lastRequest = null;

        public function request(HttpRequest $request): HttpResponse
        {
            $this->lastRequest = $request;

            return new HttpResponse(200, json_encode([
                'choices' => [[
                    'finish_reason' => 'stop',
                    'message' => ['role' => 'assistant', 'content' => '{"x":1}'],
                ]],
                'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
            ]));
        }

        public function stream(HttpRequest $request): StreamInterface
        {
            throw new RuntimeException('not used');
        }

        public function withBaseUri(string $baseUri): HttpClientInterface
        {
            return $this;
        }

        public function withHeaders(array $headers): HttpClientInterface
        {
            return $this;
        }

        public function withTimeout(float $timeout): HttpClientInterface
        {
            return $this;
        }
    };
}

test('chat() 保留模型自适应思考——推理质量不受影响', function () {
    $http = fakeReasoningHttpClient();
    $provider = new MultimodalReasoningProvider('https://api.xiaomimimo.com/v1', 'k', 'mimo-v2.5', httpClient: $http);

    $provider->chat(new UserMessage('你好'));

    expect($http->lastRequest->body)->not->toHaveKey('thinking');
});

test('MiniMax chat() 带 reasoning_split=true——思考改走独立字段，正文不混入 <think>', function () {
    $http = fakeReasoningHttpClient();
    $provider = new MiniMaxProvider('https://api.minimaxi.com/v1', 'k', 'minimax-m3', httpClient: $http);

    $provider->chat(new UserMessage('你好'));

    expect($http->lastRequest->body['reasoning_split'])->toBeTrue();
});

test('structured() 用 json_object 做兼容结构化输出——厂商 API 保证 JSON 与推理隔离', function () {
    $http = fakeReasoningHttpClient();
    $provider = new MultimodalReasoningProvider('https://api.xiaomimimo.com/v1', 'k', 'mimo-v2.5', httpClient: $http);

    $schema = ['type' => 'object', 'properties' => ['x' => ['type' => 'integer']]];
    $provider->structured(new UserMessage('给个 x'), stdClass::class, $schema);

    expect($http->lastRequest->body)->not->toHaveKey('thinking')
        ->and($http->lastRequest->body['response_format'])->toBe(['type' => 'json_object']);
});
