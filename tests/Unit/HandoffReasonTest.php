<?php

use App\Enums\HandoffReason;

test('fromAiInput 保留已知原因、未知或空值归一化为 ai_requested', function () {
    expect(HandoffReason::fromAiInput('user_requested'))->toBe(HandoffReason::UserRequested)
        ->and(HandoffReason::fromAiInput('tool_failure'))->toBe(HandoffReason::ToolFailure)
        ->and(HandoffReason::fromAiInput('  low_confidence  '))->toBe(HandoffReason::LowConfidence)
        ->and(HandoffReason::fromAiInput('user_reported_loss'))->toBe(HandoffReason::AiRequested)
        ->and(HandoffReason::fromAiInput(''))->toBe(HandoffReason::AiRequested)
        ->and(HandoffReason::fromAiInput(null))->toBe(HandoffReason::AiRequested);
});

test('aiSelectableValues 不含系统兜底的 ai_unavailable', function () {
    expect(HandoffReason::aiSelectableValues())
        ->toContain('user_requested', 'ai_requested', 'low_confidence', 'tool_failure', 'policy_required')
        ->not->toContain('ai_unavailable');
});
