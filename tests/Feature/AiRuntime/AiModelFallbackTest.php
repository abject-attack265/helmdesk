<?php

use App\Data\AiRuntime\RuntimeModelCandidateData;
use App\Enums\AiProviderProtocol;
use App\Exceptions\AllModelsExhaustedException;
use App\Exceptions\ModelAttemptTimeoutException;
use App\Services\AiRuntime\AiModelFallback;

/** @return list<RuntimeModelCandidateData> */
function candidates(int $n): array
{
    return array_map(fn (int $i): RuntimeModelCandidateData => new RuntimeModelCandidateData(
        protocol: AiProviderProtocol::OpenAI,
        credentials: ['key' => 'k'],
        model_id: "m{$i}",
        supports_image_input: false,
        supports_video_input: false,
    ), range(1, $n));
}

test('首个候选成功则只尝试一次', function () {
    $calls = 0;
    $result = (new AiModelFallback)->run(candidates(3), function ($candidate, $i) use (&$calls) {
        $calls++;

        return "ok-{$i}";
    });

    expect($result)->toBe('ok-0')->and($calls)->toBe(1);
});

test('可重试错误切换到下一个候选', function () {
    $attempted = [];
    $result = (new AiModelFallback)->run(candidates(3), function ($candidate, $i) use (&$attempted) {
        $attempted[] = $i;
        if ($i === 0) {
            throw new RuntimeException('HTTP 429 rate limit exceeded');
        }

        return "ok-{$i}";
    });

    expect($result)->toBe('ok-1')->and($attempted)->toBe([0, 1]);
});

test('不可重试错误立即抛出，不尝试后续候选', function () {
    $attempted = [];

    $run = function () use (&$attempted) {
        (new AiModelFallback)->run(candidates(3), function ($candidate, $i) use (&$attempted) {
            $attempted[] = $i;
            throw new RuntimeException('400 invalid request: context length exceeded');
        });
    };

    expect($run)->toThrow(RuntimeException::class, '400 invalid request');
    expect($attempted)->toBe([0]);
});

test('多模态数据被首个模型拒收时切换到下一个候选', function () {
    $attempted = [];
    $multimodalReject = 'HTTP 400 error during POST chat/completions: {"error":{"message":"Request Error","param":"Multimodal data is corrupted or cannot be processed."}}';

    $result = (new AiModelFallback)->run(candidates(2), function ($candidate, $i) use (&$attempted, $multimodalReject) {
        $attempted[] = $i;
        if ($i === 0) {
            throw new RuntimeException($multimodalReject);
        }

        return "ok-{$i}";
    });

    expect($result)->toBe('ok-1')->and($attempted)->toBe([0, 1]);
});

test('per-attempt 推理超时可重试，切换到下一个候选', function () {
    $attempted = [];
    $result = (new AiModelFallback)->run(candidates(2), function ($candidate, $i) use (&$attempted) {
        $attempted[] = $i;
        if ($i === 0) {
            throw new ModelAttemptTimeoutException(elapsed_seconds: 40.0, timeout_seconds: 35.0);
        }

        return "ok-{$i}";
    });

    expect($result)->toBe('ok-1')->and($attempted)->toBe([0, 1]);
});

test('全部候选可重试失败则抛 AllModelsExhausted', function () {
    expect(fn () => (new AiModelFallback)->run(candidates(2), function ($candidate, $i) {
        throw new RuntimeException('503 service unavailable');
    }))->toThrow(AllModelsExhaustedException::class);
});

test('空候选列表抛 AllModelsExhausted', function () {
    expect(fn () => (new AiModelFallback)->run([], fn () => 'x'))
        ->toThrow(AllModelsExhaustedException::class);
});

test('AllModelsExhausted 携带每个候选的失败原因', function () {
    try {
        (new AiModelFallback)->run(candidates(2), function ($candidate, $i) {
            throw new RuntimeException('500 server_error on '.$candidate->model_id);
        });
        $this->fail('应抛出 AllModelsExhaustedException');
    } catch (AllModelsExhaustedException $e) {
        expect($e->candidateErrors)->toHaveCount(2)
            ->and($e->candidateErrors[0]->getMessage())->toContain('m1')
            ->and($e->candidateErrors[1]->getMessage())->toContain('m2');
    }
});

test('isRetryable 按错误类型分类可重试性', function () {
    $fallback = new AiModelFallback;

    foreach ([
        '401 unauthorized', 'invalid api key', '429 too many requests',
        'quota_exceeded', '503 service unavailable', 'overloaded',
        'model not found', 'model does not exist',
        'Multimodal data is corrupted or cannot be processed', 'failed to fetch url',
    ] as $msg) {
        expect($fallback->isRetryable(new RuntimeException($msg)))->toBeTrue("应可重试: {$msg}");
    }

    foreach ([
        '400 bad request', 'context length exceeded', 'connection timeout',
        'invalid json in tool arguments',
    ] as $msg) {
        expect($fallback->isRetryable(new RuntimeException($msg)))->toBeFalse("应不可重试: {$msg}");
    }
});
