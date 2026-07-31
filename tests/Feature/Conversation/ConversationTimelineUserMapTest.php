<?php

use App\Actions\Conversation\BuildConversationTimelineUserMapAction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

test('时间线名称解析覆盖已移出应用 / 已软删的历史坐席', function () {
    // 曾处理过会话、之后被移出应用或停用（软删）的坐席。
    $formerAgent = User::factory()->create(['name' => '前坐席']);
    $formerAgent->delete();

    $rows = new Collection([
        (object) ['type' => 'event', 'actor_user_id' => $formerAgent->id, 'payload' => null],
    ]);

    $map = BuildConversationTimelineUserMapAction::run($rows);

    expect($map)->toHaveKey((string) $formerAgent->id)
        ->and($map[(string) $formerAgent->id])->toBe('前坐席');
});
