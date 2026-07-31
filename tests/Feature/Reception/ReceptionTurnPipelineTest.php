<?php

use App\Actions\Reception\DispatchReceptionTurnAction;
use App\Events\Reception\ReceptionAgentActivityUpdated;
use App\Jobs\Reception\FlushReceptionBufferJob;
use App\Jobs\Reception\RunReceptionTurnJob;
use App\Services\Realtime\ReceptionRealtimeNotifier;
use App\Services\Reception\ReceptionDebouncer;
use App\Services\Reception\ReceptionPipelineDispatcher;
use App\Services\Reception\ReceptionPreemptionSignal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Cache::store()->flush();
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * 构造被测调度器及其协作对象。
 *
 * @return array{0: ReceptionPipelineDispatcher, 1: ReceptionDebouncer, 2: ReceptionPreemptionSignal}
 */
function makePipelineDispatcher(): array
{
    $debouncer = new ReceptionDebouncer;
    $preemption = new ReceptionPreemptionSignal;

    return [new ReceptionPipelineDispatcher($debouncer, $preemption, app(ReceptionRealtimeNotifier::class)), $debouncer, $preemption];
}

describe('ReceptionDebouncer', function () {
    test('自适应窗口：基础 2000、每条 +700、封顶 4000', function () {
        $debouncer = new ReceptionDebouncer;

        // 自适应静默窗口：基础 2000ms，每条堆积消息 +700ms，上限封顶 4000ms。
        expect($debouncer->windowForPending(0))->toBe(2000)
            ->and($debouncer->windowForPending(1))->toBe(2000)
            ->and($debouncer->windowForPending(2))->toBe(2700)
            ->and($debouncer->windowForPending(3))->toBe(3400)
            ->and($debouncer->windowForPending(4))->toBe(4000)   // 4100 → 封顶
            ->and($debouncer->windowForPending(20))->toBe(4000);
    });

    test('acceptOnce 返回随堆积条数增长的窗口', function () {
        $debouncer = new ReceptionDebouncer;

        expect($debouncer->acceptOnce('conv-1', '你好', 'm1'))->toBe(2000)
            ->and($debouncer->acceptOnce('conv-1', '在吗', 'm2'))->toBe(2700)
            ->and($debouncer->acceptOnce('conv-1', '帮我下单', 'm3'))->toBe(3400);
    });

    test('pullPending 按顺序换行聚合并清空缓冲', function () {
        $debouncer = new ReceptionDebouncer;

        $debouncer->acceptOnce('conv-1', '你好', 'm1');
        $debouncer->acceptOnce('conv-1', '在吗', 'm2');
        $debouncer->acceptOnce('conv-1', '帮我下单', 'm3');

        $pulled = $debouncer->pullPending('conv-1');

        expect($pulled['text'])->toBe("你好\n在吗\n帮我下单")
            ->and($pulled['message_ids'])->toBe(['m1', 'm2', 'm3']);

        // 拉取后缓冲清空，再拉为 null
        expect($debouncer->pullPending('conv-1'))->toBeNull();
    });

    test('撤回移除尚未 flush 的缓冲消息', function () {
        $debouncer = new ReceptionDebouncer;

        $debouncer->acceptOnce('conv-1', '你好', 'm1');
        $debouncer->acceptOnce('conv-1', '说错了', 'm2');
        $debouncer->acceptRecall('conv-1', 'm2');
        $debouncer->acceptOnce('conv-1', '我想退货', 'm3');

        $pulled = $debouncer->pullPending('conv-1');

        expect($pulled['text'])->toBe("你好\n我想退货")
            ->and($pulled['message_ids'])->toBe(['m1', 'm3']);
    });

    test('空 messageId 不计入引用 ID 列表但保留文本', function () {
        $debouncer = new ReceptionDebouncer;

        $debouncer->acceptOnce('conv-1', '匿名渠道消息', '');

        $pulled = $debouncer->pullPending('conv-1');

        expect($pulled['text'])->toBe('匿名渠道消息')
            ->and($pulled['message_ids'])->toBe([]);
    });

    test('typing 信号把 flush 推迟到输入静默之后（含 grace）', function () {
        Carbon::setTestNow('2026-06-01 12:00:00.000');
        $debouncer = new ReceptionDebouncer;

        expect($debouncer->typingHoldRemainingMs('conv-1'))->toBe(0);

        $debouncer->noteTyping('conv-1');

        // 立即查询：剩余约 4000ms（typing 窗口上限）+ 300ms grace
        expect($debouncer->typingHoldRemainingMs('conv-1'))->toBe(4300);

        // 推进 4 秒：typing 已静默，仅剩 grace 之外的余量归零
        Carbon::setTestNow('2026-06-01 12:00:04.000');
        expect($debouncer->typingHoldRemainingMs('conv-1'))->toBe(0);
    });

    test('缓冲与 typing 状态按会话隔离', function () {
        $debouncer = new ReceptionDebouncer;

        $debouncer->acceptOnce('conv-1', '会话一', 'm1');
        $debouncer->acceptOnce('conv-2', '会话二', 'm2');

        expect($debouncer->pullPending('conv-1')['text'])->toBe('会话一')
            ->and($debouncer->pullPending('conv-2')['text'])->toBe('会话二');
    });
});

describe('ReceptionPreemptionSignal', function () {
    test('登记当前 turn 后请求抢占会命中该 turn', function () {
        $signal = new ReceptionPreemptionSignal;

        $signal->beginTurn('conv-1', 'turn-a');
        expect($signal->isPreempted('conv-1', 'turn-a'))->toBeFalse();

        $signal->requestPreemption('conv-1');

        expect($signal->isPreempted('conv-1', 'turn-a'))->toBeTrue();
    });

    test('新 turn 免疫上一轮遗留的取消标志', function () {
        $signal = new ReceptionPreemptionSignal;

        // 第一轮被抢占
        $signal->beginTurn('conv-1', 'turn-a');
        $signal->requestPreemption('conv-1');
        expect($signal->isPreempted('conv-1', 'turn-a'))->toBeTrue();

        // 第二轮用新 turnId 开跑：beginTurn 清掉旧标志，新 turn 不应被误判为抢占
        $signal->beginTurn('conv-1', 'turn-b');

        expect($signal->isPreempted('conv-1', 'turn-b'))->toBeFalse();
    });

    test('没有 turn 在运行时请求抢占不会误伤后续 turn', function () {
        $signal = new ReceptionPreemptionSignal;

        // 当前无登记 turn，requestPreemption 应为 no-op
        $signal->requestPreemption('conv-1');

        $signal->beginTurn('conv-1', 'turn-a');
        expect($signal->isPreempted('conv-1', 'turn-a'))->toBeFalse();
    });

    test('抢占信号按会话隔离', function () {
        $signal = new ReceptionPreemptionSignal;

        $signal->beginTurn('conv-1', 'turn-a');
        $signal->beginTurn('conv-2', 'turn-a');

        $signal->requestPreemption('conv-1');

        expect($signal->isPreempted('conv-1', 'turn-a'))->toBeTrue();
        expect($signal->isPreempted('conv-2', 'turn-a'))->toBeFalse();
    });

    test('turn 结束后清理登记与取消标志', function () {
        $signal = new ReceptionPreemptionSignal;

        $signal->beginTurn('conv-1', 'turn-a');
        $signal->requestPreemption('conv-1');
        $signal->endTurn('conv-1', 'turn-a');

        expect($signal->isPreempted('conv-1', 'turn-a'))->toBeFalse();

        // 结束后再请求抢占无当前 turn，不应留下标志
        $signal->requestPreemption('conv-1');
        expect($signal->isPreempted('conv-1', 'turn-a'))->toBeFalse();
    });
});

describe('ReceptionPipelineDispatcher', function () {
    test('入站访客消息缓冲聚合并派发 flush job', function () {
        Bus::fake();
        Event::fake([ReceptionAgentActivityUpdated::class]);
        [$dispatcher, $debouncer] = makePipelineDispatcher();

        $dispatcher->enqueueVisitorMessage('conv-1', '你好', 'm1');

        Bus::assertDispatched(FlushReceptionBufferJob::class, fn (FlushReceptionBufferJob $j): bool => $j->conversationId === 'conv-1');
        Event::assertDispatched(ReceptionAgentActivityUpdated::class, fn (ReceptionAgentActivityUpdated $event): bool => $event->conversationId === 'conv-1'
            && $event->active
            && $event->holdMilliseconds > 0
            && $event->holdMilliseconds <= 600000
            && $event->revision > 0);

        // 消息已进入缓冲，可被后续 pull 拿到
        expect($debouncer->pullPending('conv-1'))->toBe(['text' => '你好', 'message_ids' => ['m1'], 'media_ids' => []]);
    });

    test('连发多条都进入同一缓冲，按顺序聚合', function () {
        Bus::fake();
        [$dispatcher, $debouncer] = makePipelineDispatcher();

        $dispatcher->enqueueVisitorMessage('conv-1', '你好', 'm1');
        $dispatcher->enqueueVisitorMessage('conv-1', '在吗', 'm2');

        Bus::assertDispatchedTimes(FlushReceptionBufferJob::class, 2);
        expect($debouncer->pullPending('conv-1'))->toBe(['text' => "你好\n在吗", 'message_ids' => ['m1', 'm2'], 'media_ids' => []]);
    });

    test('turn 进行中入站新消息会请求抢占该 turn', function () {
        Bus::fake();
        [$dispatcher, , $preemption] = makePipelineDispatcher();

        // 模拟一轮 turn 正在飞行中
        $preemption->beginTurn('conv-1', 'turn-1');

        $dispatcher->enqueueVisitorMessage('conv-1', '补充一下', 'm2');

        expect($preemption->isPreempted('conv-1', 'turn-1'))->toBeTrue();
    });

    test('无 turn 在跑时入站消息不会误伤后续新 turn', function () {
        Bus::fake();
        [$dispatcher, , $preemption] = makePipelineDispatcher();

        $dispatcher->enqueueVisitorMessage('conv-1', '你好', 'm1');

        // 此后开跑的新 turn 不应命中抢占标志
        $preemption->beginTurn('conv-1', 'turn-1');

        expect($preemption->isPreempted('conv-1', 'turn-1'))->toBeFalse();
    });

    test('typing 信号委托给 debouncer', function () {
        [$dispatcher, $debouncer] = makePipelineDispatcher();

        expect($debouncer->typingHoldRemainingMs('conv-1'))->toBe(0);

        $dispatcher->noteTyping('conv-1');

        expect($debouncer->typingHoldRemainingMs('conv-1'))->toBeGreaterThan(0);
    });

    test('撤回从缓冲中移除消息', function () {
        Bus::fake();
        [$dispatcher, $debouncer] = makePipelineDispatcher();

        $dispatcher->enqueueVisitorMessage('conv-1', '你好', 'm1');
        $dispatcher->enqueueVisitorMessage('conv-1', '说错了', 'm2');
        $dispatcher->notifyMessageRecalled('conv-1', 'm2');

        expect($debouncer->pullPending('conv-1'))->toBe(['text' => '你好', 'message_ids' => ['m1'], 'media_ids' => []]);
    });
});

describe('FlushReceptionBufferJob', function () {
    test('静默窗口到期时拉取聚合并派发 turn job', function () {
        Carbon::setTestNow('2026-06-01 12:00:00.000');
        $debouncer = new ReceptionDebouncer;
        $debouncer->acceptOnce('conv-1', '你好', 'm1');
        $debouncer->acceptOnce('conv-1', '在吗', 'm2');

        // 推进超过 2 条消息的窗口（2700ms）
        Carbon::setTestNow('2026-06-01 12:00:03.000');
        Bus::fake();
        Event::fake([ReceptionAgentActivityUpdated::class]);
        $realtimeNotifier = app(ReceptionRealtimeNotifier::class);
        $realtimeNotifier->aiDebounceStarted('conv-1');

        (new FlushReceptionBufferJob('conv-1'))->handle(
            $debouncer,
            $realtimeNotifier,
            app(DispatchReceptionTurnAction::class),
        );

        Bus::assertDispatched(
            RunReceptionTurnJob::class,
            fn (RunReceptionTurnJob $job): bool => $job->conversationId === 'conv-1'
                && $job->aggregatedText === "你好\n在吗"
                && $job->messageIds === ['m1', 'm2'],
        );
        Bus::assertNotDispatched(FlushReceptionBufferJob::class);

        $activityEvents = Event::dispatched(ReceptionAgentActivityUpdated::class)
            ->map(static fn (array $arguments): ReceptionAgentActivityUpdated => $arguments[0])
            ->slice(1)
            ->values();

        expect($activityEvents)->toHaveCount(2)
            ->and($activityEvents[0]->active)->toBeTrue()
            ->and($activityEvents[0]->holdMilliseconds)->toBe(600000)
            ->and($activityEvents[1]->active)->toBeTrue()
            ->and($activityEvents[1]->holdMilliseconds)->toBe(330000);
    });

    test('静默窗口未到期时顺延重派自身，不触发 turn', function () {
        Carbon::setTestNow('2026-06-01 12:00:00.000');
        $debouncer = new ReceptionDebouncer;
        $debouncer->acceptOnce('conv-1', '你好', 'm1');

        // 仅推进 500ms，窗口（2000ms）未到期
        Carbon::setTestNow('2026-06-01 12:00:00.500');
        Bus::fake();

        (new FlushReceptionBufferJob('conv-1'))->handle(
            $debouncer,
            app(ReceptionRealtimeNotifier::class),
            app(DispatchReceptionTurnAction::class),
        );

        Bus::assertDispatched(FlushReceptionBufferJob::class);
        Bus::assertNotDispatched(RunReceptionTurnJob::class);
    });

    test('访客仍在输入时顺延重派自身', function () {
        Carbon::setTestNow('2026-06-01 12:00:00.000');
        $debouncer = new ReceptionDebouncer;
        $debouncer->acceptOnce('conv-1', '你好', 'm1');
        $debouncer->noteTyping('conv-1');

        // 推进超过静默窗口，但 typing 仍有效
        Carbon::setTestNow('2026-06-01 12:00:02.000');
        Bus::fake();

        (new FlushReceptionBufferJob('conv-1'))->handle(
            $debouncer,
            app(ReceptionRealtimeNotifier::class),
            app(DispatchReceptionTurnAction::class),
        );

        Bus::assertDispatched(FlushReceptionBufferJob::class);
        Bus::assertNotDispatched(RunReceptionTurnJob::class);
    });

    test('缓冲为空时不派发队列任务并释放 debounce 活动', function () {
        Bus::fake();
        Event::fake([ReceptionAgentActivityUpdated::class]);

        (new FlushReceptionBufferJob('conv-1'))->handle(
            new ReceptionDebouncer,
            app(ReceptionRealtimeNotifier::class),
            app(DispatchReceptionTurnAction::class),
        );

        Bus::assertNothingDispatched();
        Event::assertDispatched(
            ReceptionAgentActivityUpdated::class,
            fn (ReceptionAgentActivityUpdated $event): bool => ! $event->active
                && $event->holdMilliseconds === 0,
        );
    });

    test('已派发 turn 后重复 flush 拉到空缓冲即 no-op', function () {
        Carbon::setTestNow('2026-06-01 12:00:00.000');
        $debouncer = new ReceptionDebouncer;
        $debouncer->acceptOnce('conv-1', '你好', 'm1');
        Carbon::setTestNow('2026-06-01 12:00:03.000');

        Bus::fake();
        (new FlushReceptionBufferJob('conv-1'))->handle(
            $debouncer,
            app(ReceptionRealtimeNotifier::class),
            app(DispatchReceptionTurnAction::class),
        );
        Bus::assertDispatched(RunReceptionTurnJob::class);

        Bus::fake();
        Event::fake([ReceptionAgentActivityUpdated::class]);
        (new FlushReceptionBufferJob('conv-1'))->handle(
            $debouncer,
            app(ReceptionRealtimeNotifier::class),
            app(DispatchReceptionTurnAction::class),
        );
        Bus::assertNothingDispatched();
    });
});
