<?php

use App\Data\AiCallLog\AiCallLogDetailData;
use App\Enums\AiCallPurpose;
use App\Models\AiCallLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

test('非接待调用使用日志媒体快照展示图片', function () {
    $imageUrl = 'https://cdn.example.com/messages/photo.png';
    $log = AiCallLog::query()->create([
        'purpose' => AiCallPurpose::Assistant,
        'model_name' => 'gpt-4o',
        'system_prompts' => [],
        'available_tools' => [],
        'messages' => [[
            'role' => 'user',
            'run_id' => (string) Str::uuid(),
            'turn_id' => null,
            'created_at' => now()->toIso8601String(),
            'content' => '这是什么？',
            'media' => [['type' => 'image', 'url' => $imageUrl]],
            'conversation_message_ids' => [],
        ]],
    ]);

    $data = AiCallLogDetailData::fromLog($log, collect(), null, 'zh_CN')->toArray();

    expect($data['messages'][0]['images'])->toBe([[
        'url' => $imageUrl,
        'name' => null,
    ]]);
});
