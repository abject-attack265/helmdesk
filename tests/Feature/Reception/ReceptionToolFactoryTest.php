<?php

use App\Actions\Reception\AppendAiMessageAction;
use App\Actions\Reception\RequestHandoffAction;
use App\Data\Reception\HandoffDecisionData;
use App\Exceptions\BusinessException;
use App\Models\Conversation;
use App\Services\Reception\ReceptionPreemptionSignal;
use App\Services\Reception\ReceptionToolFactory;
use App\Services\Reception\ReceptionTurnDelivery;
use NeuronAI\Tools\Tool;

/**
 * 调用一个工具并返回其结果字符串（NeuronAI 把数组结果序列化为 JSON）。
 *
 * @param  array<string, mixed>  $inputs
 */
function runTool(Tool $tool, array $inputs): string
{
    $tool->setInputs($inputs);
    $tool->execute();

    return $tool->getResult();
}

/**
 * 构造接待工具工厂；各依赖默认用不会被调用的 mock，按需覆盖。
 */
function makeToolFactory(
    ?AppendAiMessageAction $append = null,
    ?ReceptionPreemptionSignal $preemption = null,
    ?RequestHandoffAction $handoff = null,
): ReceptionToolFactory {
    return new ReceptionToolFactory(
        $append ?? Mockery::mock(AppendAiMessageAction::class),
        $preemption ?? Mockery::mock(ReceptionPreemptionSignal::class),
        $handoff ?? Mockery::mock(RequestHandoffAction::class),
    );
}

test('respond 投递过渡气泡并把版本快照透传给投递', function () {
    $conversation = new Conversation;
    $append = Mockery::mock(AppendAiMessageAction::class);
    // 过渡气泡不引用访客消息（quoted=null），带上本轮驱动版本与 turn_id（归属本轮，供调用日志按轮叠加工具）。
    $append->shouldReceive('handle')->once()->with($conversation, '我帮您查一下，请稍候', null, 'v-1', 'turn-1');
    $preemption = Mockery::mock(ReceptionPreemptionSignal::class);
    $preemption->shouldReceive('isPreempted')->once()->with('conv-1', 'turn-1')->andReturnFalse();
    $delivery = new ReceptionTurnDelivery;

    $tool = makeToolFactory($append, $preemption)->buildRespondTool($conversation, 'conv-1', 'turn-1', $delivery, 'v-1');
    $result = runTool($tool, ['message' => '我帮您查一下，请稍候']);

    expect(json_decode($result, true))->toBe(['ok' => true])
        ->and($delivery->delivered())->toBeTrue();
});

test('respond 空消息返回 message_required，不投递', function () {
    $append = Mockery::mock(AppendAiMessageAction::class);
    $append->shouldNotReceive('handle');
    $delivery = new ReceptionTurnDelivery;

    $tool = makeToolFactory($append)->buildRespondTool(new Conversation, 'conv-1', 'turn-1', $delivery);
    $result = runTool($tool, ['message' => '  ']);

    expect(json_decode($result, true))->toBe(['error' => 'message_required'])
        ->and($delivery->delivered())->toBeFalse();
});

test('respond 本轮被抢占时跳过投递', function () {
    $append = Mockery::mock(AppendAiMessageAction::class);
    $append->shouldNotReceive('handle');
    $preemption = Mockery::mock(ReceptionPreemptionSignal::class);
    $preemption->shouldReceive('isPreempted')->once()->andReturnTrue();
    $delivery = new ReceptionTurnDelivery;

    $tool = makeToolFactory($append, $preemption)->buildRespondTool(new Conversation, 'conv-1', 'turn-1', $delivery);
    $result = runTool($tool, ['message' => '稍等']);

    expect(json_decode($result, true))->toBe(['error' => 'preempted'])
        ->and($delivery->delivered())->toBeFalse();
});

test('respond 会话已不可投递（BusinessException）返回 not_deliverable', function () {
    $append = Mockery::mock(AppendAiMessageAction::class);
    $append->shouldReceive('handle')->once()->andThrow(new BusinessException('已转人工'));
    $preemption = Mockery::mock(ReceptionPreemptionSignal::class);
    $preemption->shouldReceive('isPreempted')->once()->andReturnFalse();
    $delivery = new ReceptionTurnDelivery;

    $tool = makeToolFactory($append, $preemption)->buildRespondTool(new Conversation, 'conv-1', 'turn-1', $delivery);
    $result = runTool($tool, ['message' => '已发货']);

    expect(json_decode($result, true))->toBe(['error' => 'not_deliverable'])
        ->and($delivery->delivered())->toBeFalse();
});

test('handoff_to_human 转述转人工决策，省略空的可选字段', function () {
    $handoff = Mockery::mock(RequestHandoffAction::class);
    $handoff->shouldReceive('handle')
        ->once()
        ->with(Mockery::on(fn ($args) => true), 'user_requested', 'msg-1', '访客要求人工')
        ->andReturn(new HandoffDecisionData(
            accepted: true,
            reason: 'user_requested',
            notice: '已为您转接人工客服，请稍等。',
            human_available: true,
        ));

    $tool = makeToolFactory(handoff: $handoff)->buildHandoffTool(new Conversation, 'msg-1');
    $result = runTool($tool, ['reason' => 'user_requested', 'summary' => '访客要求人工']);

    expect(json_decode($result, true))->toBe([
        'accepted' => true,
        'reason' => 'user_requested',
        'notice' => '已为您转接人工客服，请稍等。',
        'human_available' => true,
    ]);
});

test('handoff_to_human 透传 reason 给 RequestHandoffAction 并带回营业时间字段', function () {
    // 工具回调原样转交 reason，并返回被拒分支的营业时间字段。
    $handoff = Mockery::mock(RequestHandoffAction::class);
    $handoff->shouldReceive('handle')
        ->once()
        ->with(Mockery::any(), '', null, null)
        ->andReturn(new HandoffDecisionData(
            accepted: false,
            reason: 'outside_business_hours',
            notice: '当前非工作时间，我会继续为您处理。',
            human_available: false,
            business_hours_summary: '周一至周五 9:00-18:00',
            next_available_at: '2026-06-02T09:00:00+08:00',
        ));

    $tool = makeToolFactory(handoff: $handoff)->buildHandoffTool(new Conversation, null);
    $result = runTool($tool, ['reason' => '', 'summary' => '']);

    expect(json_decode($result, true))->toBe([
        'accepted' => false,
        'reason' => 'outside_business_hours',
        'notice' => '当前非工作时间，我会继续为您处理。',
        'human_available' => false,
        'business_hours_summary' => '周一至周五 9:00-18:00',
        'next_available_at' => '2026-06-02T09:00:00+08:00',
    ]);
});
