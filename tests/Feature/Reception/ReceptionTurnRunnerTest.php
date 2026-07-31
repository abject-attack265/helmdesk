<?php

use App\Exceptions\ModelAttemptTimeoutException;
use App\Services\Reception\ReceptionPreemptionSignal;
use App\Services\Reception\ReceptionTurnRunner;
use Illuminate\Support\Facades\Cache;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;
use NeuronAI\Tools\Tool;

beforeEach(function () {
    Cache::store()->flush();
});

test('累积文本 chunk 并正常完成', function () {
    $preemption = new ReceptionPreemptionSignal;
    $preemption->beginTurn('conv-1', 'turn-a');
    $runner = new ReceptionTurnRunner($preemption);

    $events = [
        new TextChunk('m1', '您好，'),
        new TextChunk('m1', '我来帮您'),
        new TextChunk('m1', '处理。'),
    ];

    $outcome = $runner->consume($events, 'conv-1', 'turn-a');

    expect($outcome->isCompleted())->toBeTrue()
        ->and($outcome->text)->toBe('您好，我来帮您处理。');
});

test('工具调用前的中间文本被丢弃，只保留最后一次工具结果之后的答复', function () {
    $preemption = new ReceptionPreemptionSignal;
    $preemption->beginTurn('conv-1', 'turn-a');
    $runner = new ReceptionTurnRunner($preemption);

    $tool = Tool::make('knowledge_search', 'x');

    $events = [
        new TextChunk('m1', '让我查一下订单……'),  // 工具调用前的中间陈述，应被丢弃
        new ToolCallChunk($tool),
        new TextChunk('m1', '您的订单已发货，预计明天送达。'),  // 最终答复
    ];

    $outcome = $runner->consume($events, 'conv-1', 'turn-a');

    expect($outcome->text)->toBe('您的订单已发货，预计明天送达。');
});

test('消费途中检测到抢占则丢弃产物', function () {
    $preemption = new ReceptionPreemptionSignal;
    $preemption->beginTurn('conv-1', 'turn-a');
    $runner = new ReceptionTurnRunner($preemption);

    // 生成器在产出第一个 chunk 后触发抢占，模拟推理途中新访客消息到达
    $events = (function () use ($preemption) {
        yield new TextChunk('m1', '您好，');
        $preemption->requestPreemption('conv-1');
        yield new TextChunk('m1', '我来帮您处理。');
    })();

    $outcome = $runner->consume($events, 'conv-1', 'turn-a');

    expect($outcome->isPreempted())->toBeTrue()
        ->and($outcome->text)->toBe('');
});

test('空文本产出返回 completed 且文本为空', function () {
    $preemption = new ReceptionPreemptionSignal;
    $preemption->beginTurn('conv-1', 'turn-a');
    $runner = new ReceptionTurnRunner($preemption);

    $outcome = $runner->consume([], 'conv-1', 'turn-a');

    expect($outcome->isCompleted())->toBeTrue()
        ->and($outcome->text)->toBe('');
});

test('慢吐 token 超过 per-attempt 时限则抛模型超时异常', function () {
    $preemption = new ReceptionPreemptionSignal;
    $preemption->beginTurn('conv-1', 'turn-a');
    $runner = new ReceptionTurnRunner($preemption);

    // chunk 间各睡 30ms，时限设 0.05s：到第三个 chunk 边界（已过 ~60ms）即超时。
    $events = (function () {
        yield new TextChunk('m1', 'a');
        usleep(30000);
        yield new TextChunk('m1', 'b');
        usleep(30000);
        yield new TextChunk('m1', 'c');
    })();

    expect(fn () => $runner->consume($events, 'conv-1', 'turn-a', 0.05))
        ->toThrow(ModelAttemptTimeoutException::class);
});

test('未超过 per-attempt 时限则正常完成', function () {
    $preemption = new ReceptionPreemptionSignal;
    $preemption->beginTurn('conv-1', 'turn-a');
    $runner = new ReceptionTurnRunner($preemption);

    $events = [
        new TextChunk('m1', '您好，'),
        new TextChunk('m1', '已为您处理。'),
    ];

    $outcome = $runner->consume($events, 'conv-1', 'turn-a', 5.0);

    expect($outcome->isCompleted())->toBeTrue()
        ->and($outcome->text)->toBe('您好，已为您处理。');
});

test('抢占优先于超时：超时窗口内命中抢占则丢弃产物而非抛超时', function () {
    $preemption = new ReceptionPreemptionSignal;
    $preemption->beginTurn('conv-1', 'turn-a');
    $runner = new ReceptionTurnRunner($preemption);

    // 第一个 chunk 后即触发抢占，并睡过时限：下一边界先命中抢占检查（在超时检查之前）返回 preempted。
    $events = (function () use ($preemption) {
        yield new TextChunk('m1', '您好，');
        $preemption->requestPreemption('conv-1');
        usleep(60000);
        yield new TextChunk('m1', '我来帮您处理。');
    })();

    $outcome = $runner->consume($events, 'conv-1', 'turn-a', 0.05);

    expect($outcome->isPreempted())->toBeTrue()
        ->and($outcome->text)->toBe('');
});
