<?php

use App\Enums\AiModelPurpose;
use App\Models\AiUsageLog;
use App\Services\Ai\Usage\AiUsageContext;
use App\Services\Ai\Usage\UsageRecordingObserver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Observability\Events\InferenceStop;

uses(RefreshDatabase::class);

/**
 * 构造一条带 token 用量的推理结束事件。
 */
function inferenceStopWithUsage(int $input, int $output): InferenceStop
{
    $response = (new AssistantMessage('已为你处理'))->setUsage(new Usage($input, $output));

    return new InferenceStop(false, $response);
}

test('inference-stop 事件按归因上下文落一条用量记录', function () {
    $aiModelId = (string) Str::ulid();
    $conversationId = (string) Str::ulid();

    $context = new AiUsageContext(
        purpose: AiModelPurpose::ReceptionChat,
        aiModelId: $aiModelId,
        modelName: 'gpt-4o',
        conversationId: $conversationId,
    );

    (new UsageRecordingObserver($context))->onEvent('inference-stop', new stdClass, inferenceStopWithUsage(123, 45));

    expect(AiUsageLog::query()->count())->toBe(1);
    $this->assertDatabaseHas('ai_usage_logs', [
        'ai_model_id' => $aiModelId,
        'model_name' => 'gpt-4o',
        'purpose' => AiModelPurpose::ReceptionChat->value,
        'conversation_id' => $conversationId,
        'input_tokens' => 123,
        'output_tokens' => 45,
    ]);
});

test('同一 agent 的多轮 inference-stop 逐次累计为多行', function () {
    $context = new AiUsageContext(
        purpose: AiModelPurpose::Assistant,
    );
    $observer = new UsageRecordingObserver($context);

    $observer->onEvent('inference-stop', new stdClass, inferenceStopWithUsage(10, 5));
    $observer->onEvent('inference-stop', new stdClass, inferenceStopWithUsage(20, 8));

    expect(AiUsageLog::query()->sum('input_tokens'))->toBe(30)
        ->and(AiUsageLog::query()->sum('output_tokens'))->toBe(13)
        ->and(AiUsageLog::query()->count())->toBe(2);
});

test('非 inference-stop 事件与无 usage 的响应都不写记录', function () {
    $observer = new UsageRecordingObserver(new AiUsageContext(
        purpose: AiModelPurpose::BackgroundTask,
    ));

    // 其它事件忽略
    $observer->onEvent('inference-start', new stdClass, inferenceStopWithUsage(10, 5));
    // inference-stop 但响应未带 usage（如流式上游未回 usage）
    $observer->onEvent('inference-stop', new stdClass, new InferenceStop(false, new AssistantMessage('无用量')));

    expect(AiUsageLog::query()->count())->toBe(0);
});
