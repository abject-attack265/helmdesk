<?php

use App\Services\AiRuntime\OpenAICompatibleProvider;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\HttpClient\HttpClientInterface;
use NeuronAI\HttpClient\HttpRequest;
use NeuronAI\HttpClient\HttpResponse;
use NeuronAI\HttpClient\StreamInterface;

/**
 * 捕获最后一次请求并返回固定 chat completion 响应的假 HTTP 客户端。
 */
function fakeOpenAiHttpClient(): HttpClientInterface
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
                    'message' => ['role' => 'assistant', 'content' => '{"candidates":["好的"]}'],
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

test('structured 降级为 json_object 并把 schema 拼进 system prompt', function () {
    $http = fakeOpenAiHttpClient();
    $provider = new OpenAICompatibleProvider('https://api.deepseek.com/v1', 'sk-x', 'deepseek-chat', httpClient: $http);
    $provider->systemPrompt('你是客服回复助手。');

    $schema = ['type' => 'object', 'properties' => ['candidates' => ['type' => 'array']]];
    $provider->structured(new UserMessage('帮我写一条回复'), stdClass::class, $schema);

    $body = $http->lastRequest->body;
    $systemText = $body['messages'][0]['content'][0]['text'];
    expect($body['response_format'])->toBe(['type' => 'json_object'])
        ->and($body['messages'][0]['role'])->toBe('system')
        ->and($systemText)->toContain('你是客服回复助手。')
        ->and($systemText)->toContain('OUTPUT FORMAT CONSTRAINTS')
        ->and($systemText)->toContain(json_encode($schema));
});

test('structured 调用后还原 parameters / system，不污染复用实例的后续 chat', function () {
    $http = fakeOpenAiHttpClient();
    $provider = new OpenAICompatibleProvider('https://api.deepseek.com/v1', 'sk-x', 'deepseek-chat', httpClient: $http);
    $provider->systemPrompt('你是客服回复助手。');

    $schema = ['type' => 'object', 'properties' => ['candidates' => ['type' => 'array']]];
    $provider->structured(new UserMessage('帮我写一条回复'), stdClass::class, $schema);

    // 复用同一 provider 实例再跑一次普通 chat：不应残留 json_object 与 schema 提示词。
    $provider->chat(new UserMessage('再来一条'));

    $body = $http->lastRequest->body;
    $systemText = $body['messages'][0]['content'][0]['text'] ?? '';
    expect($body['response_format'] ?? null)->toBeNull()
        ->and($systemText)->toContain('你是客服回复助手。')
        ->and($systemText)->not->toContain('OUTPUT FORMAT CONSTRAINTS')
        ->and($systemText)->not->toContain(json_encode($schema));
});
