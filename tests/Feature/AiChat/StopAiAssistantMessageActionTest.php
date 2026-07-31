<?php

use App\Actions\AiChat\StopAiAssistantMessageAction;
use App\Services\AiChat\AiChatStreamSignal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

test('它为有效轮次置取消标志并返回 stopped', function () {
    createSystemSettings();
    $roundId = Str::uuid()->toString();

    $result = app(StopAiAssistantMessageAction::class)->handle($roundId);

    expect($result)->toBe(['stopped' => true])
        ->and(app(AiChatStreamSignal::class)->isCancelled($roundId))->toBeTrue();
});

test('它拒绝非法的 round_id', function () {
    createSystemSettings();

    expect(fn () => app(StopAiAssistantMessageAction::class)->handle('not-a-uuid'))
        ->toThrow(ValidationException::class);
});
